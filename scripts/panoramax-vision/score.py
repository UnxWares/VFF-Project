"""Note « la gare est visible » des vignettes Panoramax (tri des photos de rue des gares, 23/11).

Modèle : CLIP ViT-B-16 entraîné sur DataComp-1B (laion/CLIP-ViT-B-16-DataComp.XL-s13B-b90K, poids sous licence MIT),
lu par open_clip (licence MIT). Classement sans entraînement : chaque vignette est comparée à des descriptions de ce
qu'une photo de gare montre (bâtiment voyageurs, quai, voie, abri, panneau) et de ce qu'une photo hors sujet
montre (route, maisons, haie, parking...). La note est la part de probabilité des descriptions « gare ».

    python -I score.py <dossier des vignettes> <fichier des notes>

Lit `<id>.jpg` dans le dossier, note celles qui n'ont pas encore de note pour la version des descriptions en cours,
écrit le fichier JSON {"model", "prompts", "scores": {id: note}} (réécrit par lots, de façon atomique) et les
vecteurs d'image à côté (`embeddings.npz`), pour régler les descriptions sans relire les images. Processeur
seulement par défaut ; VISION_THREADS (4) et VISION_BATCH (16) bornent la charge.
"""
import json
import os
import sys

MODEL = 'hf-hub:laion/CLIP-ViT-B-16-DataComp.XL-s13B-b90K'
ARCH, REPO, WEIGHTS = 'ViT-B-16', 'laion/CLIP-ViT-B-16-DataComp.XL-s13B-b90K', 'open_clip_pytorch_model.bin'
MEAN, STD = (0.48145466, 0.4578275, 0.40821073), (0.26862954, 0.26130258, 0.27577711)

# Version des descriptions : la changer impose de tout renoter (les vecteurs d'image restent valables).
PROMPTS_VERSION = 'v3'
# v3 (23/11) : descriptions courtes. Mesure sur 100 photos retenues par v2 (graine 2312) : v2 laissait passer 23 %
# de photos hors sujet (maisons, immeubles, hôtels, notés comme des bâtiments de gare) ; v3, au seuil 0,7, n'en
# garde qu'une sur 35 et garde 28 des 36 photos de gare. Réglage sur les 250 photos des deux échantillons, mesure
# sur un troisième (panoramax-vision.md).
POSITIVE = [
    'a train station',
    'a railway platform',
    'railway tracks',
]
NEGATIVE = [
    'a road',
    'a street',
    'houses',
    'a parking lot',
    'trees',
    'a field',
]
TEMPLATES = ['a photo of {}.', 'a street-level photo of {}.']


def load(open_clip, torch):
    """Modèle, prétraitement et découpeur de texte. Poids lus en projection mémoire (mmap) : le pic reste sous
    1 Gio (la lecture ordinaire du fichier de 600 Mo doublait la mémoire et dépassait la borne du conteneur)."""
    from huggingface_hub import hf_hub_download
    model = open_clip.create_model(ARCH, pretrained=None)
    state = torch.load(hf_hub_download(REPO, WEIGHTS), map_location='cpu', mmap=True, weights_only=True)
    model.load_state_dict(state)
    del state
    model.eval()
    preprocess = open_clip.image_transform(224, is_train=False, mean=MEAN, std=STD)
    return model, preprocess, open_clip.get_tokenizer(ARCH)


def text_features(model, tokenizer, torch):
    feats = []
    for label in POSITIVE + NEGATIVE:
        tokens = tokenizer([t.format(label) for t in TEMPLATES])
        with torch.no_grad():
            f = model.encode_text(tokens)
        f = f / f.norm(dim=-1, keepdim=True)
        f = f.mean(dim=0)
        feats.append(f / f.norm())
    return torch.stack(feats)


def score_from_embeddings(img, txt, scale, torch):
    """Part de probabilité des descriptions « gare » (softmax à la température du modèle)."""
    probs = (scale * img @ txt.T).softmax(dim=-1)
    return probs[:, :len(POSITIVE)].sum(dim=-1)


def main():
    thumbs, out = sys.argv[1], sys.argv[2]
    # open_clip, ftfy et regex sont installés à part (dossier monté par run.sh), l'image n'a que torch.
    if os.environ.get('VISION_PYLIB'):
        sys.path.insert(0, os.environ['VISION_PYLIB'])
    import numpy as np
    import open_clip
    import torch
    from PIL import Image

    torch.set_num_threads(int(os.environ.get('VISION_THREADS', '4')))
    batch = int(os.environ.get('VISION_BATCH', '16'))
    device = 'cuda' if os.environ.get('VISION_DEVICE') == 'cuda' and torch.cuda.is_available() else 'cpu'

    data = {'model': MODEL, 'prompts': PROMPTS_VERSION, 'scores': {}}
    if os.path.exists(out):
        with open(out) as h:
            old = json.load(h)
        if old.get('model') == data['model'] and old.get('prompts') == PROMPTS_VERSION:
            data = old
    emb_file = os.path.join(os.path.dirname(os.path.abspath(out)), 'embeddings.npz')
    emb = dict(np.load(emb_file)) if os.path.exists(emb_file) else {}

    ids = sorted(f[:-4] for f in os.listdir(thumbs) if f.endswith('.jpg'))
    todo = [i for i in ids if i not in data['scores']]
    print(f'{len(ids)} vignettes, {len(todo)} à noter ({device})', flush=True)
    if not todo:
        return

    model, preprocess, tokenizer = load(open_clip, torch)
    txt = text_features(model, tokenizer, torch)
    scale = model.logit_scale.exp().item()
    model.transformer = None  # tour du texte inutile ensuite : mémoire rendue
    model.to(device)
    txt = txt.to(device)

    def save():
        tmp = out + '.tmp'
        with open(tmp, 'w') as h:
            json.dump(data, h, sort_keys=True)
        os.replace(tmp, out)
        np.savez(emb_file + '.tmp.npz', **emb)
        os.replace(emb_file + '.tmp.npz', emb_file)

    for k in range(0, len(todo), batch):
        chunk = todo[k:k + batch]
        names = [i for i in chunk if i not in emb]
        if names:
            imgs = []
            ok = []
            for i in names:
                try:
                    imgs.append(preprocess(Image.open(os.path.join(thumbs, i + '.jpg')).convert('RGB')))
                    ok.append(i)
                except OSError as e:
                    print('illisible', i, e, flush=True)
            if imgs:
                with torch.no_grad():
                    f = model.encode_image(torch.stack(imgs).to(device))
                f = f / f.norm(dim=-1, keepdim=True)
                for i, v in zip(ok, f.cpu().numpy().astype(np.float16)):
                    emb[i] = v
        have = [i for i in chunk if i in emb]
        if have:
            img = torch.tensor(np.stack([emb[i] for i in have]).astype(np.float32), device=device)
            for i, s in zip(have, score_from_embeddings(img, txt, scale, torch).tolist()):
                data['scores'][i] = round(float(s), 4)
        save()
        print(f'{min(k + batch, len(todo))}/{len(todo)}', flush=True)


if __name__ == '__main__':
    main()

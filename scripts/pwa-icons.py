#!/usr/bin/env python3
"""Génère les icônes de l'application web (public/icons) à partir du logo officiel.

Le logo (resources/images/vff.png, carré 1024 px, disque sur fond transparent) n'est ni modifié ni déformé :
il est seulement réduit, toujours avec le même rapport largeur / hauteur.

  - icon-192.png, icon-512.png : purpose « any », le disque du logo avec sa transparence.
  - icon-maskable-192.png, icon-maskable-512.png : purpose « maskable ». Le logo occupe 70 % du côté, centré, sur
    un aplat orange de la charte (#ff6500) qui remplit tout le carré : n'importe quel masque (cercle, carré arrondi,
    goutte) laisse le logo entier, car la zone de sécurité est un disque de 80 % du côté.
  - apple-touch-icon.png (180 px) : iOS ne gère pas la transparence, même habillage que les icônes maskable.

Usage : python3 scripts/pwa-icons.py   (Pillow requis ; rien d'autre n'est lu ni écrit hors public/icons)
"""
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
LOGO = ROOT / "resources/images/vff.png"
OUT = ROOT / "public/icons"
ORANGE = (255, 101, 0, 255)
MASKABLE_RATIO = 0.70


def scaled(logo: Image.Image, side: int) -> Image.Image:
    return logo.resize((side, side), Image.Resampling.LANCZOS)


def on_orange(logo: Image.Image, side: int) -> Image.Image:
    canvas = Image.new("RGBA", (side, side), ORANGE)
    inner = round(side * MASKABLE_RATIO)
    offset = (side - inner) // 2
    canvas.alpha_composite(scaled(logo, inner), (offset, offset))
    return canvas


def main() -> None:
    logo = Image.open(LOGO).convert("RGBA")
    if logo.width != logo.height:
        raise SystemExit("Le logo doit être carré.")
    OUT.mkdir(parents=True, exist_ok=True)
    for side in (192, 512):
        scaled(logo, side).save(OUT / f"icon-{side}.png", optimize=True)
        on_orange(logo, side).save(OUT / f"icon-maskable-{side}.png", optimize=True)
    on_orange(logo, 180).convert("RGB").save(OUT / "apple-touch-icon.png", optimize=True)


if __name__ == "__main__":
    main()

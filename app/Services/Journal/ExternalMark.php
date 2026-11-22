<?php

namespace App\Services\Journal;

use League\CommonMark\Node\Inline\AbstractInline;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Marque posée à la fin d'un lien externe d'un article (JournalRenderer) : l'icône « nouvel onglet »
 * du site et sa phrase lue par les lecteurs d'écran, comme partout ailleurs (GUIDE, section 5.6).
 * Nœud et rendu dans la même classe : le rendu ne sert qu'à lui.
 */
final class ExternalMark extends AbstractInline implements NodeRendererInterface
{
    public function __construct(private readonly string $label = '')
    {
        parent::__construct();
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        $label = $node instanceof self ? $node->label : '';

        return (string) new HtmlElement('i', ['class' => 'bi bi-box-arrow-up-right vff-ext', 'aria-hidden' => 'true'], '')
            .new HtmlElement('span', ['class' => 'vff-sr-only'], ' ('.htmlspecialchars($label, ENT_QUOTES).')');
    }
}

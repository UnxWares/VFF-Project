<?php

namespace App\Enums;

/**
 * Nature d'un document cité par le contexte d'un accident (accident_references.kind) : rapport d'enquête,
 * texte officiel (Journal officiel), débat ou question parlementaire, presse, livre, article savant, document ou
 * inventaire d'archives, monument ou mémorial, page web (histoire locale, association), forum, encyclopédie,
 * données structurées, vidéo.
 */
enum AccidentReferenceKind: string
{
    case Report = 'report';
    case Official = 'official';
    case Parliament = 'parliament';
    case Press = 'press';
    case Book = 'book';
    case Scholarly = 'scholarly';
    case Archive = 'archive';
    case Memorial = 'memorial';
    case Web = 'web';
    case Forum = 'forum';
    case Encyclopedia = 'encyclopedia';
    case Data = 'data';
    case Video = 'video';
}

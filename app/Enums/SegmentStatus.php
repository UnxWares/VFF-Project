<?php

namespace App\Enums;

enum SegmentStatus: string
{
    case InService = 'in_service';
    case SingleTrack = 'single_track';
    case Abandoned = 'abandoned';
    case Dismantled = 'dismantled';
    case Razed = 'razed';
}

<?php

namespace App\Enums;

enum TrafficLight: string
{
    case GRAY = 'GRAY';
    case BLUE = 'BLUE';
    case YELLOW = 'YELLOW';
    case PURPLE = 'PURPLE';
    case ORANGE = 'ORANGE';
    case GREEN = 'GREEN';
    case DARK_GREEN = 'DARK_GREEN';
    case RED = 'RED';
}

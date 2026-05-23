<?php

namespace Husail\EdiSdk\Schema;

/**
 * Side to which padding is applied when serializing a field.
 *
 *   LEFT → zeros/chars on the left (default for NUMERIC)
 *   RIGHT → spaces/chars on the right (default for ALPHA)
 */
enum PaddingSide: string
{
    case LEFT  = 'left';
    case RIGHT = 'right';
}

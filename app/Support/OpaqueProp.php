<?php

namespace App\Support;

use stdClass;

/**
 * Inertia walks every nested array in page props, one node at a time, to
 * look for lazy props. Large plain-data blobs (design documents, the widget
 * catalog) have thousands of nodes and nothing lazy inside, so that walk
 * costs seconds per page. Converting them to objects stops the walk while
 * producing exactly the same JSON for the browser.
 */
final class OpaqueProp
{
    /**
     * @param  array<array-key, mixed>|null  $value
     * @return stdClass|array<array-key, mixed>|null
     */
    public static function from(?array $value): stdClass|array|null
    {
        if ($value === null) {
            return null;
        }

        return json_decode((string) json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
    }
}

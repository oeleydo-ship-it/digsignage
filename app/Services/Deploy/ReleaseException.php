<?php

namespace App\Services\Deploy;

use RuntimeException;

/**
 * A problem with a release package or install that is safe to show to the
 * platform administrator as-is.
 */
class ReleaseException extends RuntimeException {}

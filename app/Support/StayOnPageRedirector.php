<?php

namespace App\Support;

use Illuminate\Routing\Redirector;

class StayOnPageRedirector extends Redirector
{
    /**
     * @param  array<string, string>  $headers
     */
    public function back($status = 302, $headers = [], $fallback = false)
    {
        return $this->createRedirect(StayOnPage::url($this->generator->getRequest(), $fallback), $status, $headers);
    }
}

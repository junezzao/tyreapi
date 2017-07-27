<?php namespace App\Http;

use Illuminate\Http\Request as BaseRequest;

class Request extends BaseRequest
{
    protected $foo;

    public function __construct()
    {
        parent::__construct();

        // Do your set up here, for example:
        $this->yourAttribute = 'foo';
    }

    /**
     * Get a unique fingerprint for the request / route / IP address.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public function fingerprint()
    {
        if (! $this->route()) {
            throw new RuntimeException('Unable to generate fingerprint. Route unavailable.');
        }

        return sha1(
            implode('|', $this->route()->methods()).
            '|'.$this->route()->domain().
            '|'.$this->route()->uri().
            '|'.$this->ip()
        );
    }
}

<?php namespace IET_OU\OU_Embed;

/**
 * A Guzzle-6 based HTTP client for Essence.
 *
 * @copyright © 2017 The Open University.
 * @author    Nick Freear, 08-July-2017.
 */

use Essence\Http\Client as EssenceHttpClient;
use GuzzleHttp\Client;

class GuzzleClient implements EssenceHttpClient
{
    protected $client;

    public function __construct(array $options = [])
    {
        $this->client = new \GuzzleHttp\Client($options);
        // $this->client->setUserAgent('ou-embed');
    }

    public function get($url)
    {
        $response = $this->client->request('GET', $url);

        $body = (string) $response->getBody();

        return $body;
    }

    public function setUserAgent($agent)
    {
        // no-op.
    }
}

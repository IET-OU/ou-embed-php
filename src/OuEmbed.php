<?php namespace IET_OU\OU_Embed;

/**
 * A simple wrapper around the oEmbed-powered 'simexis/embed' library, with some OU-specific goodness sprinkled on top.
 *
 * @copyright © 2017 The Open University.
 * @author    Nick Freear, 07-July-2017.
 * @link      http://oembed.com oEmbed specification.
 * @link      https://packagist.org/packages/essence/essence  No proxy support :(!
 */

use GuzzleHttp\Client;

class OuEmbed
{
    const OU_OEMBED_URL = 'https://embed.open.ac.uk/oembed?url=%s';
    const OU_PROXY = 'http://wwwcache.open.ac.uk:80';
    const OU_PODCAST_REGEX = '@\/\/podcast.open.ac.uk\/@';

    /**
     * Attempt to create a HTML embed code/snippet for a URL.
     *
     * @param $url string
     * @return null | object An object with a 'html', 'title' and other properites.
     */
    public static function resolve($url)
    {
        $embed = self::resolveOupodcastEmbed($url);

        if (! $embed) {
            // $info = \Embed\Embed::create( 'https://www.youtube.com/watch?v=vUxEC5c-Rc0',
            // [ 'resolver' => [ 'config' => [ CURLOPT_PROXY => 'http://wwwcache.open.ac.uk:80' ]] ]
            // /*, [ 'resolver' => [ 'class' => 'Embed\\RequestResolvers\\Guzzle5' ]]*/ );
            $info = \Embed\Embed::create($url, [ 'resolver' => [ 'config' => [ CURLOPT_PROXY => env('APP_PROXY') ]] ]);

            $embed = (object) [
                'type' => $info->type,
                'title' => $info->title,
                'url' => $info->url,
                'html' => $info->getCode(),
                'date' => $info->publishedDate,
            ];
        }

        self::debug($embed);
        // header('X-ou-embed-php-01: ' . json_encode($embed));

        return $embed;
    }

    /**
     * As "podcast.open.ac.uk" does not implement oEmbed auto-discovery, we intercept these calls.
     */
    protected static function resolveOupodcastEmbed($url)
    {
        $embed = null;

        if (preg_match(self::OU_PODCAST_REGEX, $url)) {
            $request_url = sprintf(self::OU_OEMBED_URL, urlencode($url));

            $client = new \GuzzleHttp\Client([ 'base_uri' => $request_url, 'proxy' => env('APP_PROXY') ]);
            $response = $client->request('GET');

            $body = (string) $response->getBody();

            // header('X-presentation-embed-02: ' . json_encode( $body ));

            $embed = json_decode($body);
        }

        return $embed;
    }

    protected static function debug($obj)
    {
        static $count = 0;
        header(sprintf('X-ou-embed-php-%02d: %s', $count, json_encode($obj)));
        $count++;
    }
}

// End.

<?php namespace IET_OU\OU_Embed;

/**
 * A simple wrapper around the oEmbed-powered 'simexis/embed' library, with some OU-specific goodness sprinkled on top.
 *
 * @copyright © 2017 The Open University.
 * @author    Nick Freear, 07-July-2017.
 * @link      http://oembed.com oEmbed specification.
 * @link      https://packagist.org/packages/essence/essence  No proxy support :(!
 */

use Essence\Essence;
use Essence\Di\Container as EssenceInject;
use GuzzleHttp\Client as Guzzle;
use IET_OU\OU_Embed\GuzzleClient as OuGuzzle;

use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;

/* use Doctrine\Common\Cache\FilesystemCache;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage; */

class OuEmbed
{
    const OU_OEMBED_URL = 'https://embed.open.ac.uk/oembed?url=%s';
    const OU_PROXY = 'http://wwwcache.open.ac.uk:80';
    const OU_PODCAST_REGEX = '@\/\/podcast.open.ac.uk\/@';

    protected static $headers = [ 'User-Agent' => 'ou-embed-php' ];

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
            $essence = new Essence([
                // the container will return a unique instance of CustomHttpClient
                // each time an HTTP client is needed
                'Http' => EssenceInject::unique(function () {
                    return new OuGuzzle([ 'proxy' => env('APP_PROXY'),
                        'handler' => self::getStack(), 'headers' => self::$headers ]);
                })
            ]);

            $media = $essence->extract($url);

            $embed = $media ? (object) $media->properties() : null;
        }

        self::debug($embed);

        return $embed;
    }

    protected static function legacyResolveEmbed($url)
    {
        $info = \Embed\Embed::create($url, [ 'resolver' => [ 'config' => [ CURLOPT_PROXY => env('APP_PROXY'),
            'handler' => self::getStack() ]] ]);

        return (object) [
            'type' => $info->type,
            'title' => $info->title,
            'url' => $info->url,
            'html' => $info->getCode(),
            'date' => $info->publishedDate,
        ];
    }

    /**
     * As "podcast.open.ac.uk" does not implement oEmbed auto-discovery, we intercept these calls.
     *
     * @param $url string
     * @return null | object An object with a 'html', 'title' and other properites.
     */
    protected static function resolveOupodcastEmbed($url)
    {
        $embed = null;

        if (preg_match(self::OU_PODCAST_REGEX, $url)) {
            $request_url = sprintf(self::OU_OEMBED_URL, urlencode($url));
            $headers = self::$headers;

            $client = new Guzzle([ 'proxy' => env('APP_PROXY'), 'handler' => self::getStack(), 'headers' => $headers ]);

            $response = $client->request('GET', $request_url);

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

    protected static function getStack()
    {
        // Create default HandlerStack
        $stack = HandlerStack::create();

        // Add this middleware to the top with `push`
        $stack->push(new CacheMiddleware(), 'cache');

        /* $stack->push(
            new CacheMiddleware(
                new PrivateCacheStrategy(
                    new DoctrineCacheStorage(new FilesystemCache('/tmp/'))
                )
            ),
            'cache'
        ); */

        return $stack;
    }
}

// End.

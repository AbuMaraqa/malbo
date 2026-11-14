<?php

namespace Modules\Items\Support;

/**
 * A product's video: a YouTube or Vimeo link, or a direct https link to an .mp4 file. The storefront
 * embeds YouTube from youtube-nocookie.com and Vimeo with "do not track", so a visitor who never
 * plays the video is not tracked.
 */
final class ProductVideo
{
    private const YOUTUBE = '~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})(?:[?&#/].*)?$~';

    private const VIMEO = '~^https?://(?:www\.|player\.)?vimeo\.com/(?:video/)?(\d{6,12})(?:[?#/].*)?$~';

    private const FILE = '~^https://[^\s?#]+\.mp4(?:[?#]\S*)?$~i';

    public static function isValid(string $url): bool
    {
        return self::embed($url) !== null;
    }

    /**
     * @return array{type: 'youtube'|'vimeo'|'file', src: string}|null
     */
    public static function embed(?string $url): ?array
    {
        $url = trim((string) $url);

        if ($url === '' || strlen($url) > 500) {
            return null;
        }

        if (preg_match(self::YOUTUBE, $url, $match)) {
            return ['type' => 'youtube', 'src' => 'https://www.youtube-nocookie.com/embed/'.$match[1]];
        }

        if (preg_match(self::VIMEO, $url, $match)) {
            return ['type' => 'vimeo', 'src' => 'https://player.vimeo.com/video/'.$match[1].'?dnt=1'];
        }

        if (preg_match(self::FILE, $url) && filter_var($url, FILTER_VALIDATE_URL)) {
            return ['type' => 'file', 'src' => $url];
        }

        return null;
    }
}

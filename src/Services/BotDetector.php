<?php
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
/**
 * Detects bot/crawler requests based on the User-Agent header.
 * Result is cached for the lifetime of the PHP process.
 */

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

class BotDetector
{
    private static ?bool $cached = null;

    /**
     * Returns true when the User-Agent belongs to a known bot or crawler.
     * Pass a custom $ua to bypass the cached current-request value.
     */
    public static function isBot(?string $ua = null): bool
    {
        if ($ua !== null) {
            return self::matches($ua);
        }

        if (self::$cached === null) {
            self::$cached = self::matches(
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
            );
        }

        return self::$cached;
    }

    private static function matches(string $ua): bool
    {
        if ($ua === '') {
            return true; // no UA → almost certainly automated
        }

        return (bool) preg_match(
            // "bot" catches: Googlebot, bingbot, YandexBot, SemrushBot, AhrefsBot,
            // DotBot, MJ12bot, ClaudeBot, GPTBot, PerplexityBot, LinkedInBot, Twitterbot …
            '/bot|spider|crawl|scrape|slurp'
            // Command-line / HTTP-library clients
            . '|wget|curl\/|python-requests|python-urllib|Go-http-client|libwww|Scrapy|mechanize'
            // Headless browsers
            . '|PhantomJS|HeadlessChrome'
            // Social-preview agents that don't carry "bot"
            . '|facebookexternalhit|WhatsApp\/|Slackbot|TelegramBot|Discordbot'
            // AI agents without "bot" in the name
            . '|anthropic-ai|cohere-ai|OAI-SearchBot|meta-externalagent|AI2Bot'
            // Misc
            . '|PetalBot|ia_archiver|Applebot'
            . '/i',
            $ua
        );
    }
}

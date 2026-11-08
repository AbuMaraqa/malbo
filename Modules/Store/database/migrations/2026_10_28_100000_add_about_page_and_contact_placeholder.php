<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the "About us" system page, and moves the starter texts from {whatsapp}, {phone} and
 * {email} to {contact}, which lists only the channels the store set (the old sentences read
 * "WhatsApp our contact channels" when one was missing). Only the starter sentences are
 * rewritten: a sentence the owner edited no longer matches and stays as it is.
 */
return new class extends Migration
{
    /** locale => [starter sentence => new sentence] */
    public const REWRITES = [
        'ar' => [
            'راسلنا على واتساب {whatsapp} في أسرع وقت.' => 'تواصل معنا في أسرع وقت عبر {contact}.',
            '<p>واتساب {whatsapp}، هاتف {phone}، بريد {email}.</p>' => '<p>يسعدنا تواصلك عبر {contact}.</p>',
            'بمراسلتنا على {email} أو واتساب {whatsapp}.' => 'بمراسلتنا عبر {contact}.',
            'راسلنا على واتساب {whatsapp} أو على {email} مع رقم الطلب' => 'راسلنا عبر {contact} مع رقم الطلب',
            'تواصل معنا قبل الطلب على {whatsapp} وسنؤكد لك.' => 'تواصل معنا قبل الطلب عبر {contact} وسنؤكد لك.',
            'لأي سؤال عن طلبك: واتساب {whatsapp}، هاتف {phone}، بريد {email}.' => 'لأي سؤال عن طلبك تواصل معنا عبر {contact}.',
            'لأي استفسار: {email} أو {phone}.' => 'لأي استفسار تواصل معنا عبر {contact}.',
        ],
        'en' => [
            'Message us on WhatsApp {whatsapp} as soon as you can.' => 'Reach us as soon as you can via {contact}.',
            '<p>WhatsApp {whatsapp}, phone {phone}, email {email}.</p>' => '<p>We are happy to hear from you via {contact}.</p>',
            'by writing to {email} or on WhatsApp {whatsapp}.' => 'by contacting us via {contact}.',
            'Message us on WhatsApp {whatsapp} or at {email} with your order number' => 'Contact us via {contact} with your order number',
            'message us before ordering on {whatsapp} and we will confirm.' => 'contact us before ordering via {contact} and we will confirm.',
            'Questions about your order: WhatsApp {whatsapp}, phone {phone}, email {email}.' => 'For any question about your order, contact us via {contact}.',
            'Questions: {email} or {phone}.' => 'Questions? Contact us via {contact}.',
        ],
    ];

    public function up(): void
    {
        DB::table('store_page_translations')
            ->whereIn('store_page_id', DB::table('store_pages')->whereNotNull('key')->select('id'))
            ->whereNotNull('body')
            ->orderBy('id')
            ->get(['id', 'locale', 'body'])
            ->each(function (object $row) {
                $body = strtr($row->body, self::REWRITES[$row->locale] ?? []);
                if ($body !== $row->body) {
                    DB::table('store_page_translations')->where('id', $row->id)->update(['body' => $body]);
                }
            });

        if (DB::table('store_pages')->where('key', 'about')->exists()) {
            return;
        }

        $slug = DB::table('store_pages')->where('slug', 'about-us')->exists() ? 'about-our-store' : 'about-us';
        $now = now();
        $id = DB::table('store_pages')->insertGetId([
            'key' => 'about', 'slug' => $slug, 'is_published' => true, 'show_in_footer' => true,
            'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now,
        ]);

        foreach (['ar' => 'من نحن', 'en' => 'About us'] as $locale => $title) {
            $template = module_path('Store', "resources/page-templates/about.{$locale}.html");
            DB::table('store_page_translations')->insert([
                'store_page_id' => $id,
                'locale' => $locale,
                'title' => $title,
                'body' => is_file($template) ? trim((string) file_get_contents($template)) : null,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('store_pages')->where('key', 'about')->delete();

        DB::table('store_page_translations')->whereNotNull('body')->orderBy('id')->get(['id', 'locale', 'body'])
            ->each(function (object $row) {
                $body = strtr($row->body, array_flip(self::REWRITES[$row->locale] ?? []));
                if ($body !== $row->body) {
                    DB::table('store_page_translations')->where('id', $row->id)->update(['body' => $body]);
                }
            });
    }
};

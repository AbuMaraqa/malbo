<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, h, type VNode } from 'vue';

import type { StoreSeo } from './types';

/**
 * Everything the page's <head> tells search engines and link previews, from the `seo` prop:
 * title, description, canonical, hreflang, OpenGraph and JSON-LD. Written with `h()` because a
 * Vue template drops a <script> tag, and <Head> needs the JSON as the tag's plain text child. The
 * JSON comes from the server with `<` and `&` escaped, so it cannot close the tag.
 */
const page = usePage();
const seo = computed(() => page.props.seo as StoreSeo);

function tags(): VNode[] {
    const { canonical, alternates, x_default: xDefault, description, noindex, og, twitter_card: twitterCard, json_ld: jsonLd } = seo.value;

    const meta = (key: string, attributes: Record<string, string>): VNode => h('meta', { 'head-key': key, ...attributes });
    const nodes: VNode[] = [];

    if (description) {
        nodes.push(meta('description', { name: 'description', content: description }));
    }

    if (noindex) {
        nodes.push(meta('robots', { name: 'robots', content: 'noindex' }));
    } else {
        if (canonical) {
            nodes.push(h('link', { 'head-key': 'canonical', rel: 'canonical', href: canonical }));
        }

        for (const [code, url] of Object.entries(alternates)) {
            nodes.push(h('link', { 'head-key': `hreflang-${code}`, rel: 'alternate', hreflang: code, href: url }));
        }

        if (xDefault) {
            nodes.push(h('link', { 'head-key': 'hreflang-default', rel: 'alternate', hreflang: 'x-default', href: xDefault }));
        }
    }

    nodes.push(meta('og:site_name', { property: 'og:site_name', content: og.site_name }));
    nodes.push(meta('og:title', { property: 'og:title', content: og.title }));
    nodes.push(meta('og:type', { property: 'og:type', content: og.type }));
    nodes.push(meta('og:url', { property: 'og:url', content: og.url }));
    nodes.push(meta('og:locale', { property: 'og:locale', content: og.locale }));

    if (og.description) {
        nodes.push(meta('og:description', { property: 'og:description', content: og.description }));
    }

    if (og.image) {
        nodes.push(meta('og:image', { property: 'og:image', content: og.image }));
    }

    nodes.push(meta('twitter:card', { name: 'twitter:card', content: twitterCard }));

    jsonLd.forEach((schema, index) => {
        nodes.push(h('script', { 'head-key': `json-ld-${index}`, type: 'application/ld+json' }, schema));
    });

    return nodes;
}

const SeoHead = () => h(Head, { title: seo.value.title }, () => tags());
</script>

<template>
    <SeoHead />
</template>

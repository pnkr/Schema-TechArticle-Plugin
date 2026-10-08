<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Schemaorg.techarticle
 *
 * @copyright   (C) 2026 Panagiotis Kiriakopoulos. <https://www.github.com/pnkr>
 * @author      Panagiotis Kiriakopoulos <kiriakopoulos.p@gmail.com>
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Pnkr\Plugin\Schemaorg\TechArticle\Extension;

use Joomla\CMS\Event\Plugin\System\Schemaorg\BeforeCompileHeadEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Schemaorg\SchemaorgPluginTrait;
use Joomla\CMS\Schemaorg\SchemaorgPrepareDateTrait;
use Joomla\CMS\Schemaorg\SchemaorgPrepareImageTrait;
use Joomla\Event\Priority;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Schemaorg Plugin
 *
 * @since  1.0.0
 */
final class TechArticle extends CMSPlugin implements SubscriberInterface
{
    use SchemaorgPluginTrait;
    use SchemaorgPrepareDateTrait;
    use SchemaorgPrepareImageTrait;

    /**
     * Allowed values for the author @type
     *
     * @var    string[]
     * @since  1.0.0
     */
    private const AUTHOR_TYPES = ['Person', 'Organization'];

    /**
     * Allowed values for proficiencyLevel
     *
     * @var    string[]
     * @since  1.0.0
     */
    private const PROFICIENCY_LEVELS = ['Beginner', 'Expert'];

    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * @since  1.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * The name of the schema form
     *
     * @var   string
     * @since 1.0.0
     */
    // Align pluginName with subform name 'TechArticle' for onSchemaPrepareForm injection
    protected $pluginName = 'TechArticle';

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onSchemaPrepareForm'       => 'onSchemaPrepareForm',
            'onSchemaBeforeCompileHead' => ['onSchemaBeforeCompileHead', Priority::BELOW_NORMAL],
        ];
    }

    /**
     * Cleanup all TechArticle types
     *
     * @param   BeforeCompileHeadEvent  $event  The given event
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onSchemaBeforeCompileHead(BeforeCompileHeadEvent $event): void
    {
        $schema = $event->getSchema();

        $graph = $schema->get('@graph') ?? [];

        foreach ($graph as &$entry) {
            if (!\is_array($entry) || ($entry['@type'] ?? '') !== 'TechArticle') {
                continue;
            }

            $entry = $this->prepareTechArticle($entry);
        }

        unset($entry);

        $schema->set('@graph', $graph);
    }

    /**
     * Normalize a single TechArticle entry into valid Schema.org output
     *
     * @param   array  $entry  The TechArticle entry
     *
     * @return  array
     *
     * @since   1.0.0
     */
    private function prepareTechArticle(array $entry): array
    {
        // Never let generic fields override JSON-LD keywords such as @id, @type or @context
        if (isset($entry['genericField'])) {
            $entry['genericField'] = array_filter(
                $this->toList($entry['genericField']),
                static fn (array $row): bool => !str_starts_with(trim((string) ($row['genericTitle'] ?? '')), '@')
            );
        }

        if (!empty($entry['image'])) {
            $entry['image'] = $this->prepareImage($entry['image']);
        }

        foreach (['datePublished', 'dateModified'] as $key) {
            if (!empty($entry[$key])) {
                $entry[$key] = $this->prepareDate($entry[$key]);
            }
        }

        if (isset($entry['author'])) {
            $authors = [];

            foreach ($this->toList($entry['author']) as $author) {
                if ($this->hasValues($author)) {
                    $authors[] = $this->prepareAuthor($author);
                }
            }

            unset($entry['author']);

            if ($authors) {
                $entry['author'] = \count($authors) === 1 ? $authors[0] : $authors;
            }
        }

        if (isset($entry['publisher']) && \is_array($entry['publisher'])) {
            $entry['publisher']['@type'] = 'Organization';

            if (!empty($entry['publisher']['logo'])) {
                $entry['publisher']['logo'] = [
                    '@type' => 'ImageObject',
                    'url'   => $this->prepareImage($entry['publisher']['logo']),
                ];
            } else {
                unset($entry['publisher']['logo']);
            }
        }

        if (isset($entry['audience']) && \is_array($entry['audience'])) {
            $entry['audience']['@type'] = 'Audience';
        }

        // Drop single-object subforms the editor left empty
        foreach (['publisher', 'audience'] as $key) {
            if (isset($entry[$key]) && (!\is_array($entry[$key]) || !$this->hasValues($entry[$key]))) {
                unset($entry[$key]);
            }
        }

        if (isset($entry['proficiencyLevel']) && !\in_array($entry['proficiencyLevel'], self::PROFICIENCY_LEVELS, true)) {
            unset($entry['proficiencyLevel']);
        }

        // The form stores "1" / "0" / ""; Schema.org expects a boolean
        if (isset($entry['isAccessibleForFree'])) {
            $free = (string) $entry['isAccessibleForFree'];

            unset($entry['isAccessibleForFree']);

            if ($free !== '') {
                $entry['isAccessibleForFree'] = $free === '1';
            }
        }

        if (isset($entry['wordCount'])) {
            $words = (int) $entry['wordCount'];

            unset($entry['wordCount']);

            if ($words > 0) {
                $entry['wordCount'] = $words;
            }
        }

        // The form stores minutes; Schema.org expects an ISO 8601 duration
        if (isset($entry['timeRequired'])) {
            $minutes = (int) $entry['timeRequired'];

            unset($entry['timeRequired']);

            if ($minutes > 0) {
                $entry['timeRequired'] = 'PT' . $minutes . 'M';
            }
        }

        return $entry;
    }

    /**
     * Normalize an author (Person or Organization)
     *
     * @param   array  $author  The author data
     *
     * @return  array
     *
     * @since   1.0.0
     */
    private function prepareAuthor(array $author): array
    {
        $type = (string) ($author['@type'] ?? '');

        $author['@type'] = \in_array($type, self::AUTHOR_TYPES, true) ? $type : 'Person';

        return $author;
    }

    /**
     * Convert a repeatable subform value into a list of rows
     *
     * @param   mixed  $value  The subform value
     *
     * @return  array[]
     *
     * @since   1.0.0
     */
    private function toList($value): array
    {
        if (!\is_array($value) && !\is_object($value)) {
            return [];
        }

        return array_values(array_map(static fn ($row): array => (array) $row, (array) $value));
    }

    /**
     * Check whether an object has any non-empty value besides JSON-LD keywords
     *
     * @param   array  $data  The data to check
     *
     * @return  boolean
     *
     * @since   1.0.0
     */
    private function hasValues(array $data): bool
    {
        foreach ($data as $key => $value) {
            if (!str_starts_with((string) $key, '@') && $value !== '' && $value !== null && $value !== []) {
                return true;
            }
        }

        return false;
    }
}

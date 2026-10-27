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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Schemaorg\SchemaorgPluginTrait;
use Joomla\CMS\Schemaorg\SchemaorgPrepareDateTrait;
use Joomla\CMS\Schemaorg\SchemaorgPrepareImageTrait;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\Priority;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

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
    use DatabaseAwareTrait;
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
     * Maximum headline length recommended by Google
     *
     * @var    integer
     * @since  1.1.0
     */
    private const HEADLINE_LENGTH = 110;

    /**
     * Length of the description generated from the article text
     *
     * @var    integer
     * @since  1.1.0
     */
    private const DESCRIPTION_LENGTH = 160;

    /**
     * The article shown on the current page, false if there is none
     *
     * @var    object|false|null
     * @since  1.1.0
     */
    private $article = null;

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

            $entry = $this->prepareTechArticle($entry, $graph);
        }

        unset($entry);

        $schema->set('@graph', $graph);
    }

    /**
     * Normalize a single TechArticle entry into valid Schema.org output
     *
     * @param   array  $entry  The TechArticle entry
     * @param   array  $graph  The whole schema graph
     *
     * @return  array
     *
     * @since   1.0.0
     */
    private function prepareTechArticle(array $entry, array $graph): array
    {
        if ($this->params->get('autofill', 1)) {
            $entry = $this->fillFromArticle($entry);
        }

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

        if (!isset($entry['publisher'])) {
            $publisher = $this->getDefaultPublisher($graph);

            if ($publisher) {
                $entry['publisher'] = $publisher;
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
     * Fill the fields the editor left empty with data from the article
     *
     * @param   array  $entry  The TechArticle entry
     *
     * @return  array
     *
     * @since   1.1.0
     */
    private function fillFromArticle(array $entry): array
    {
        $article = $this->getArticle();

        if (!$article) {
            return $entry;
        }

        $text = trim(strip_tags((string) $article->introtext . ' ' . (string) $article->fulltext));
        $text = html_entity_decode(preg_replace('/\s+/u', ' ', $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $images = new Registry($article->images);

        $language = $article->language === '*' || $article->language === ''
            ? $this->getApplication()->getLanguage()->getTag()
            : $article->language;

        $url = Route::link(
            'site',
            RouteHelper::getArticleRoute($article->id . ':' . $article->alias, $article->catid, $article->language),
            true,
            Route::TLS_IGNORE,
            true
        );

        $defaults = [
            'headline'       => HTMLHelper::_('string.truncate', (string) $article->title, self::HEADLINE_LENGTH, true, false),
            'description'    => trim((string) $article->metadesc) ?: HTMLHelper::_('string.truncate', $text, self::DESCRIPTION_LENGTH, true, false),
            'image'          => $images->get('image_fulltext') ?: $images->get('image_intro'),
            'datePublished'  => $article->publish_up ?: $article->created,
            'dateModified'   => $article->modified ?: ($article->publish_up ?: $article->created),
            'articleSection' => $article->category_title,
            'keywords'       => trim((string) $article->metakey),
            'inLanguage'     => $language,
            'wordCount'      => $text === '' ? 0 : preg_match_all('/[\p{L}\p{N}]+/u', $text),
            'url'            => $url,
        ];

        foreach ($defaults as $key => $value) {
            if ($this->isBlank($entry[$key] ?? null) && !$this->isBlank($value)) {
                $entry[$key] = $value;
            }
        }

        if ($this->isBlank($entry['mainEntityOfPage'] ?? null) && !$this->isBlank($entry['url'] ?? null)) {
            $entry['mainEntityOfPage'] = $entry['url'];
        }

        $hasAuthor = false;

        foreach ($this->toList($entry['author'] ?? []) as $author) {
            $hasAuthor = $hasAuthor || $this->hasValues($author);
        }

        $authorName = trim((string) $article->created_by_alias) ?: trim((string) $article->author_name);

        if (!$hasAuthor && $authorName !== '') {
            $entry['author'] = [['@type' => 'Person', 'name' => $authorName]];
        }

        return $entry;
    }

    /**
     * Load the article shown on the current page
     *
     * @return  object|null
     *
     * @since   1.1.0
     */
    private function getArticle(): ?object
    {
        if ($this->article !== null) {
            return $this->article ?: null;
        }

        $this->article = false;

        $input = $this->getApplication()->getInput();

        if ($input->getCmd('option') !== 'com_content' || $input->getCmd('view') !== 'article') {
            return null;
        }

        $id = $input->getInt('id');

        if ($id <= 0) {
            return null;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(
                $db->quoteName(
                    [
                        'a.id', 'a.alias', 'a.catid', 'a.title', 'a.introtext', 'a.fulltext', 'a.images',
                        'a.metadesc', 'a.metakey', 'a.language', 'a.created', 'a.modified', 'a.publish_up',
                        'a.created_by_alias', 'c.title', 'u.name',
                    ],
                    [
                        'id', 'alias', 'catid', 'title', 'introtext', 'fulltext', 'images',
                        'metadesc', 'metakey', 'language', 'created', 'modified', 'publish_up',
                        'created_by_alias', 'category_title', 'author_name',
                    ]
                )
            )
            ->from($db->quoteName('#__content', 'a'))
            ->join('LEFT', $db->quoteName('#__categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'))
            ->join('LEFT', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('a.created_by'))
            ->where($db->quoteName('a.id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->article = $db->setQuery($query)->loadObject() ?: false;

        return $this->article ?: null;
    }

    /**
     * Build the publisher used when the article does not set one
     *
     * @param   array  $graph  The whole schema graph
     *
     * @return  array|null
     *
     * @since   1.1.0
     */
    private function getDefaultPublisher(array $graph): ?array
    {
        switch ($this->params->get('publisher', 'site')) {
            case 'site':
                // Reference the site's Organization/Person from the System - Schema.org plugin
                foreach ($graph as $node) {
                    if (\is_array($node) && ($node['@type'] ?? '') === 'WebSite' && !empty($node['publisher']['@id'])) {
                        return ['@id' => $node['publisher']['@id']];
                    }
                }

                foreach ($graph as $node) {
                    if (\is_array($node) && !empty($node['@id']) && \in_array($node['@type'] ?? '', self::AUTHOR_TYPES, true)) {
                        return ['@id' => $node['@id']];
                    }
                }

                return null;

            case 'custom':
                $name = trim((string) $this->params->get('publisher_name', ''));

                if ($name === '') {
                    return null;
                }

                $publisher = ['@type' => 'Organization', 'name' => $name];
                $url       = trim((string) $this->params->get('publisher_url', ''));
                $logo      = $this->params->get('publisher_logo', '');

                if ($url !== '') {
                    $publisher['url'] = $url;
                }

                if (!empty($logo)) {
                    $publisher['logo'] = ['@type' => 'ImageObject', 'url' => $this->prepareImage($logo)];
                }

                return $publisher;

            default:
                return null;
        }
    }

    /**
     * Check whether a form value is empty
     *
     * @param   mixed  $value  The value to check
     *
     * @return  boolean
     *
     * @since   1.1.0
     */
    private function isBlank($value): bool
    {
        return $value === null || $value === '' || $value === [] || $value === 0 || $value === '0';
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

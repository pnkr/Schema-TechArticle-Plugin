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

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;

/**
 * Installation script for the Schema.org - TechArticle plugin
 *
 * @since  1.2.0
 */
class PlgSchemaorgTechArticleInstallerScript implements InstallerScriptInterface
{
    /**
     * Minimum supported Joomla version
     *
     * @var    string
     * @since  1.2.0
     */
    private const MINIMUM_JOOMLA = '6.0.0';

    /**
     * Minimum supported PHP version
     *
     * @var    string
     * @since  1.2.0
     */
    private const MINIMUM_PHP = '8.3.0';

    /**
     * Abort the installation when the Joomla or PHP version is too old
     *
     * @param   string            $type     The type of change (install, update, discover_install, uninstall)
     * @param   InstallerAdapter  $adapter  The adapter calling this method
     *
     * @return  boolean
     *
     * @since   1.2.0
     */
    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        $app = Factory::getApplication();

        // The plugin's own language files are not loaded yet during a fresh install
        $app->getLanguage()->load('plg_schemaorg_techarticle.sys', $adapter->getParent()->getPath('source'));

        if (version_compare(JVERSION, self::MINIMUM_JOOMLA, '<')) {
            $app->enqueueMessage(Text::sprintf('PLG_SCHEMAORG_TECHARTICLE_INSTALL_MINIMUM_JOOMLA', self::MINIMUM_JOOMLA), 'error');

            return false;
        }

        if (version_compare(PHP_VERSION, self::MINIMUM_PHP, '<')) {
            $app->enqueueMessage(Text::sprintf('PLG_SCHEMAORG_TECHARTICLE_INSTALL_MINIMUM_PHP', self::MINIMUM_PHP), 'error');

            return false;
        }

        return true;
    }

    /**
     * Runs on a fresh install
     *
     * @param   InstallerAdapter  $adapter  The adapter calling this method
     *
     * @return  boolean
     *
     * @since   1.2.0
     */
    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    /**
     * Runs on update
     *
     * @param   InstallerAdapter  $adapter  The adapter calling this method
     *
     * @return  boolean
     *
     * @since   1.2.0
     */
    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    /**
     * Runs on uninstall
     *
     * @param   InstallerAdapter  $adapter  The adapter calling this method
     *
     * @return  boolean
     *
     * @since   1.2.0
     */
    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    /**
     * Runs after install, update or discover_install
     *
     * @param   string            $type     The type of change
     * @param   InstallerAdapter  $adapter  The adapter calling this method
     *
     * @return  boolean
     *
     * @since   1.2.0
     */
    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        return true;
    }
}

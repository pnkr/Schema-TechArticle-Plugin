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

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Pnkr\Plugin\Schemaorg\TechArticle\Extension\TechArticle;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            static function (Container $container) {
                $plugin = new TechArticle(
                    (array) PluginHelper::getPlugin('schemaorg', 'techarticle')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};

<?php

defined('TYPO3') or die();

use Ipf\NewsMan\Controller\SubscribeController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

/*
 * Registers the content element (CType) and attaches its FlexForm.
 *
 * ExtensionUtility::registerPlugin() is public API and the modern way to do
 * this; the extension manager no longer reads ext_emconf.php for composer
 * packages (see TYPO3 deprecation 108345).
 */
ExtensionUtility::registerPlugin(
    'Newsman',
    'Subscribe',
    'LLL:EXT:newsman/Resources/Private/Language/locallang.xlf:plugin.subscribe',
    'EXT:newsman/Resources/Public/Icons/Extension.svg',
    'newsman',
    'LLL:EXT:newsman/Resources/Private/Language/locallang.xlf:plugin.description',
    'FILE:EXT:newsman/Configuration/FlexForms/Subscribe.xml'
);

/*
 * Registers the allowed controller/action combinations.
 *
 * This is normally done by ExtensionUtility::configurePlugin(), but that helper
 * also emits `tt_content.newsman_subscribe =< lib.contentElement` with
 * templateName = Generic at "defaultContentRendering", i.e. after every site
 * configuration and after this extension's own
 * Configuration/TypoScript/setup.typoscript. A site package that redefines
 * lib.contentElement as a FLUIDTEMPLATE (a common pattern that derives the
 * template name from the CType) would then be overridden, the numbered
 * "20 = EXTBASEPLUGIN" child would be dropped by FLUIDTEMPLATE, and the
 * content element would render empty.
 *
 * ExtensionUtility::registerControllerActions() would avoid the TypoScript but
 * is marked @internal, so it is not part of the public contract. The array
 * written below is Extbase's documented configuration structure and is what
 * that helper writes anyway, without the dependency on an internal API.
 *
 * The alias is the short class name without the "Controller" suffix and is
 * also what Extbase derives from the plugin name ("Subscribe"), which is why the
 * controller class is named SubscribeController.
 */
$controllerClassName = SubscribeController::class;
$controllerAlias = 'Subscribe';

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']['Newsman']['plugins']['Subscribe']['controllers'][$controllerClassName] = [
    'className' => $controllerClassName,
    'alias' => $controllerAlias,
    'actions' => ['subscribe'],
    // The action talks to a remote service and reads the submitted address, so
    // its response must never end up in a page cache.
    'nonCacheableActions' => ['subscribe'],
];

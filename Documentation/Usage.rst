.. _usage:

=====
Usage
=====

Content element
===============

Create a content element in the page and choose **Newsman Subscribe**
(``CType: newsman_subscribe``). The element is registered by the extension
itself, so no TypoScript or TypoScript object needs to be copied into the site
package.

FlexForm settings
=================

.. list-table::
   :header-rows: 1
   :widths: 30 70

   * - Field
     - Description
   * - ``list``
     - The mailing list, e.g. ``newsletter@example.com``. Required
   * - ``successMessage``
     - Text shown instead of the default success message. A label key
       (``LLL:EXT:newsman/...:success.default``) is also accepted and translated
   * - ``emailLabel``
     - Text of the field label
   * - ``emailPlaceholder``
     - Placeholder of the input
   * - ``buttonLabel``
     - Text of the submit button

The three form texts are plain texts rather than labels, because the site
brings its own wording.

Behaviour of the form
=====================

The field is posted into the plugin namespace
(``tx_newsman_subscribe[email]``), which is why the form carries
``name="tx_newsman_subscribe"``. The address is validated before anything is
sent to Mailman, so an invalid address produces a validation error and no
request leaves TYPO3.

The action is registered as a non-cacheable action, so the response to a
submission is never served from a page cache.

Styling
=======

The template loads the stylesheet itself with ``<f:asset.css>``, so no
TypoScript is needed; ``EXT:newsman/Resources/Public/Css/newsman.css`` is
published with the page.

.. list-table::
   :header-rows: 1
   :widths: 40 60

   * - Class
     - Element
   * - ``newsman-subscribe``
     - Wrapper of the whole form
   * - ``newsman-subscribe__form``
     - The ``<form>`` element
   * - ``newsman-subscribe__field``
     - Wrapper of field and label
   * - ``newsman-subscribe__label``
     - Label of the email field, visually hidden, read out by screen readers
   * - ``newsman-subscribe__input``
     - The email input
   * - ``newsman-subscribe__submit``
     - The submit button
   * - ``newsman-subscribe__message``
     - Base class of a message
   * - ``newsman-subscribe__message--success``
     - Success message
   * - ``newsman-subscribe__message--error``
     - Error message, additionally marked with ``role="alert"``

The bundled CSS only styles the form itself; the design of the page around it
comes from the site package, which can overwrite these classes.

Site integration
================

The plugin is registered as ``EXTBASEPLUGIN`` from the extension's own
``Configuration/TypoScript/setup.typoscript`` — deliberately **not** via
``ExtensionUtility::configurePlugin()``. That helper emits
``tt_content.newsman_subscribe =< lib.contentElement`` with
``templateName = Generic`` at ``defaultContentRendering``, i.e. after every site
configuration. A site package that redefines ``lib.contentElement`` as a
``FLUIDTEMPLATE`` (a common pattern that derives the template name from the
CType) would then be overridden, the numbered ``20 = EXTBASEPLUGIN`` child would
be dropped by ``FLUIDTEMPLATE``, and the element would render empty.

Because site configuration is applied after extension TypoScript, a site package
that dispatches content elements through a ``CASE`` has to repeat the key
itself. A site package that wants to put its own texts and design around the
form overrides the object with a ``FLUIDTEMPLATE`` again, keeps the plugin as
its child ``20`` and calls that child from its template:

.. code-block:: typoscript

   tt_content = CASE
   # ...
   tt_content.newsman_subscribe =< lib.contentElement
   tt_content.newsman_subscribe {
     templateName = NewsmanSubscribe
     20 = EXTBASEPLUGIN
     20 {
       extensionName = Newsman
       pluginName = Subscribe
     }
   }

.. code-block:: html

   <!-- Content/NewsmanSubscribe.fluid.html -->
   <f:cObject typoscriptObjectPath="tt_content.{data.CType}.20" data="{data}" table="tt_content"/>

``FLUIDTEMPLATE`` does not render numbered children by itself, which is why the
template calls the child the way the core does it in
``EXT:fluid_styled_content/Resources/Private/Templates/Generic.fluid.html``.

.. _usage-multiple-lists:

Several lists on one site
=========================

Because the list is a FlexForm field, every content element can subscribe to a
different list. The forms are independent; each one uses the address and the
credentials from the global extension settings.

Translating the form
=====================

All backend labels and the fixed messages are labels in
``EXT:newsman/Resources/Private/Language/locallang.xlf``. The texts of field
label, placeholder and button are FlexForm fields and are therefore translated
per site language with the normal record translation of the content element.

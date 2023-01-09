<?php
namespace MediaWiki\Skins\Vector\Components;

use Title;

/**
 * VectorComponentLanguageButton component
 */
class VectorComponentLanguageDropdown implements VectorComponent {
	private const CLASS_PROGRESSIVE = 'mw-ui-progressive';
	/** @var string */
	private $label;
	/** @var string */
	private $ariaLabel;
	/** @var string */
	private $class;
	/** @var int */
	private $numLanguages;
	/** @var array */
	private $menuContentsData;
	/** @var Title|null */
	private $title;

	/**
	 * @param string $label human readable
	 * @param string $ariaLabel label for accessibility
	 * @param string $class of the dropdown component
	 * @param int $numLanguages
	 * @param string $itemHTML the HTML of the list e.g. `<li>...</li>`
	 * @param string $beforePortlet no known usages. Perhaps can be removed in future
	 * @param string $afterPortlet used by Extension:ULS
	 * @param Title|null $title
	 */
	public function __construct(
		string $label, string $ariaLabel, string $class, int $numLanguages,
		// @todo: replace with >MenuContents class.
		string $itemHTML, string $beforePortlet = '', string $afterPortlet = '', $title = null
	) {
		$this->label = $label;
		$this->ariaLabel = $ariaLabel;
		$this->class = $class;
		$this->numLanguages = $numLanguages;
		$this->menuContentsData = [
			'html-items' => $itemHTML,
			'html-before-portal' => $beforePortlet,
			'html-after-portal' => $afterPortlet,
		];
		$this->title = $title;
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		$title = $this->title;
		$isContentPage = $title && $title->exists() && $title->isContentPage();
		// if page doesn't exist or if it's not a content page, we should
		// display a less prominent "language" button, without a label, and
		// quiet instead of progressive. For this reason some default values
		// should be updated for this case. (T316559)
		if ( !$isContentPage ) {
			$icon = '<span class="mw-ui-icon mw-ui-icon-wikimedia-language"></span>';
			$label = '';
			$headingClass = 'mw-ui-button mw-ui-quiet mw-portlet-lang-heading-empty';
			$checkboxClass = 'mw-interlanguage-selector-empty';
		} else {
			$icon = '<span class="mw-ui-icon mw-ui-icon-wikimedia-language-progressive"></span>';
			$label = $this->label;
			$headingClass = 'mw-ui-button mw-ui-quiet '
				. self::CLASS_PROGRESSIVE . ' mw-portlet-lang-heading-' . strval( $this->numLanguages );
			$checkboxClass = 'mw-interlanguage-selector';
		}
		$dropdown = new VectorComponentDropdown( 'p-lang-btn', $label, $this->class );

		$dropdownData = $dropdown->getTemplateData();
		// override default heading class.
		$dropdownData['heading-class'] = $headingClass;
		// ext.uls.interface attaches click handler to this selector.
		$dropdownData['checkbox-class'] = $checkboxClass;
		// Override header icon (currently no way to do this using constructor)
		$dropdownData['html-vector-heading-icon'] = $icon;
		$dropdownData['aria-label'] = $this->ariaLabel;
		$dropdownData['is-language-selector-empty'] = !$isContentPage;

		return $dropdownData + $this->menuContentsData;
	}
}

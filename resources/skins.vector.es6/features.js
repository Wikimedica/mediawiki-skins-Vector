/** @interface MwApi */

let /** @type {MwApi} */ api;
const debounce = require( /** @type {string} */ ( 'mediawiki.util' ) ).debounce;

/**
 * Saves preference to user preferences if user is logged in
 *
 * @param {string} feature
 * @param {boolean} enabled
 */
function save( feature, enabled ) {
	if ( !mw.user.isAnon() ) {
		debounce( function () {
			api = api || new mw.Api();
			api.saveOption( 'vector-' + feature, enabled ? 1 : 0 );
		}, 500 )();
	}
}

/**
 *
 * @param {string} name feature name
 * @param {boolean} [override] option to force enabled or disabled state.
 *
 * @return {boolean} The new feature state (false=disabled, true=enabled).
 * @throws {Error} if unknown feature toggled.
 */
function toggleBodyClasses( name, override ) {
	const featureClassEnabled = 'vector-feature-' + name + '-enabled',
		classList = document.body.classList,
		featureClassDisabled = 'vector-feature-' + name + '-disabled';

	if ( classList.contains( featureClassDisabled ) || override === true ) {
		classList.remove( featureClassDisabled );
		classList.add( featureClassEnabled );
		return true;
	} else if ( classList.contains( featureClassEnabled ) || override === false ) {
		classList.add( featureClassDisabled );
		classList.remove( featureClassEnabled );
		return false;
	} else {
		throw new Error( 'Attempt to toggle unknown feature: ' + name );
	}
}

/**
 *
 * @param {string} name feature name
 * @param {boolean} [override] option to force enabled or disabled state.
 *
 * @return {boolean} The new feature state (false=disabled, true=enabled).
 * @throws {Error} if unknown feature toggled.
 */
function toggleDocClasses( name, override ) {
	const featureClassEnabled = `vector-feature-${name}-enabled`,
		classList = document.documentElement.classList,
		featureClassDisabled = `vector-feature-${name}-disabled`;

	if ( classList.contains( featureClassDisabled ) || override === true ) {
		classList.remove( featureClassDisabled );
		classList.add( featureClassEnabled );
		return true;
	} else if ( classList.contains( featureClassEnabled ) || override === false ) {
		classList.add( featureClassDisabled );
		classList.remove( featureClassEnabled );
		return false;
	} else {
		// Perhaps we are dealing with cached HTML ?
		// FIXME: Can be removed and replaced with throw new Error when cache is clear.
		return toggleBodyClasses( name, override );
	}
}

/**
 * @param {string} name
 * @throws {Error} if unknown feature toggled.
 */
function toggle( name ) {
	const featureState = toggleDocClasses( name );
	save( name, featureState );
}

/**
 * Checks if the feature is enabled.
 *
 * @param {string} name
 * @return {boolean}
 */
function isEnabled( name ) {
	const className = `vector-feature-${name}-enabled`;
	return document.documentElement.classList.contains( className ) ||
		// FIXME: For cached HTML. Remove after one train cycle.
		document.body.classList.contains( className );
}

module.exports = { isEnabled, toggle, toggleDocClasses };

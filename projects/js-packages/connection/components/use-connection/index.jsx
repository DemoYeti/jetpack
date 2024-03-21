import restApi from '@automattic/jetpack-api';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from 'react';
import { STORE_ID } from '../../state/store';

const initialState = window?.JP_CONNECTION_INITIAL_STATE ? window.JP_CONNECTION_INITIAL_STATE : {};

export default ( {
	registrationNonce = initialState.registrationNonce,
	apiRoot = initialState.apiRoot,
	apiNonce = initialState.apiNonce,
	redirectUri,
	autoTrigger,
	from,
	skipUserConnection,
} = {} ) => {
	const { registerSite, connectUser, refreshConnectedPlugins } = useDispatch( STORE_ID );

	const registrationError = useSelect( select => select( STORE_ID ).getRegistrationError() );
	const {
		siteIsRegistering,
		userIsConnecting,
		userConnectionData,
		connectedPlugins,
		connectionErrors,
		isRegistered,
		isUserConnected,
		hasConnectedOwner,
		isOfflineMode,
	} = useSelect( select => ( {
		siteIsRegistering: select( STORE_ID ).getSiteIsRegistering(),
		userIsConnecting: select( STORE_ID ).getUserIsConnecting(),
		userConnectionData: select( STORE_ID ).getUserConnectionData(),
		connectedPlugins: select( STORE_ID ).getConnectedPlugins(),
		connectionErrors: select( STORE_ID ).getConnectionErrors(),
		isOfflineMode: select( STORE_ID ).getIsOfflineMode(),
		...select( STORE_ID ).getConnectionStatus(),
	} ) );

	/**
	 * Calls the registerSite action to register the site.
	 *
	 * @returns {Promise} - Promise which resolves when the product status is activated.
	 */
	const registerSiteOnly = () => {
		return registerSite( { registrationNonce, redirectUri } );
	};

	/**
	 * User register process handler.
	 *
	 * @returns {Promise} - Promise which resolves when the product status is activated.
	 */
	const handleConnectUser = () => {
		if ( ! skipUserConnection ) {
			return connectUser( { from, redirectUri } );
		} else if ( redirectUri ) {
			window.location = redirectUri;
			return Promise.resolve( redirectUri );
		}

		return Promise.resolve();
	};

	/**
	 * Site register process handler.
	 *
	 * It handles the process to register the site, considering also the site registration status.
	 * When the site is registered, it will try to only register the user.
	 * Otherwise, will try to register the user right after the site was successfully registered.
	 *
	 * @param {Event} [e] - Event that dispatched handleRegisterSite
	 * @returns {Promise}   Promise when running the registration process. Otherwise, nothing.
	 */
	const handleRegisterSite = e => {
		e && e.preventDefault();

		if ( isRegistered ) {
			return handleConnectUser();
		}

		return registerSiteOnly().then( () => {
			return handleConnectUser();
		} );
	};

	/**
	 * Site register process handler.
	 *
	 * It handles the process to register only the site.
	 * When the site is registered, it will return a fulfilled promise.
	 *
	 * @param {Event} [e] - Event that dispatched handleRegisterSiteOnly
	 * @returns {Promise}   Promise when running the registration process. Otherwise, nothing.
	 */
	const handleRegisterSiteOnly = e => {
		e && e.preventDefault();

		if ( isRegistered ) {
			return Promise.resolve();
		}

		return registerSiteOnly();
	};

	/**
	 * Initialize/Setup the REST API.
	 */
	useEffect( () => {
		restApi.setApiRoot( apiRoot );
		restApi.setApiNonce( apiNonce );
	}, [ apiRoot, apiNonce ] );

	/**
	 * Auto-trigger the flow, only do it once.
	 */
	useEffect( () => {
		if ( autoTrigger && ! siteIsRegistering && ! userIsConnecting ) {
			handleRegisterSite();
		}
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	return {
		handleRegisterSite,
		handleRegisterSiteOnly,
		handleConnectUser,
		refreshConnectedPlugins,
		isRegistered,
		isUserConnected,
		siteIsRegistering,
		userIsConnecting,
		registrationError,
		userConnectionData,
		hasConnectedOwner,
		connectedPlugins,
		connectionErrors,
		isOfflineMode,
	};
};

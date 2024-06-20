import { Spinner, Text, useBreakpointMatch } from '@automattic/jetpack-components';
import { useSelect } from '@wordpress/data';
import { dateI18n } from '@wordpress/date';
import { sprintf, __ } from '@wordpress/i18n';
import { Icon, check, chevronDown, chevronUp } from '@wordpress/icons';
import clsx from 'clsx';
import React, { useState, useCallback, useContext } from 'react';
import useScanHistory from '../../hooks/use-scan-history';
import { STORE_ID } from '../../state/store';
import ThreatSeverityBadge from '../severity';
import styles from './styles.module.scss';

const PaidAccordionContext = React.createContext();

export const PaidAccordionItem = ( {
	id,
	title,
	label,
	icon,
	fixable,
	severity,
	children,
	firstDetected,
	fixedOn,
	onOpen,
} ) => {
	const accordionData = useContext( PaidAccordionContext );
	const open = accordionData?.open === id;
	const setOpen = accordionData?.setOpen;
	const threatsAreFixing = useSelect( select => select( STORE_ID ).getThreatsAreFixing() );
	const { viewingScanHistory } = useScanHistory();

	const bodyClassNames = clsx( styles[ 'accordion-body' ], {
		[ styles[ 'accordion-body-open' ] ]: open,
		[ styles[ 'accordion-body-close' ] ]: ! open,
	} );

	const handleClick = useCallback( () => {
		if ( ! open ) {
			onOpen?.();
		}
		setOpen( current => {
			return current === id ? null : id;
		} );
	}, [ open, onOpen, setOpen, id ] );

	const [ isSmall ] = useBreakpointMatch( [ 'sm', 'lg' ], [ null, '<' ] );

	const FixDetails = ( { date, isFixed } ) => (
		<span className={ styles[ isFixed ? 'is-fixed' : 'is-ignored' ] }>
			{ isFixed
				? sprintf(
						/* translators: %s: Fixed on date */
						__( 'Threat fixed %s', 'jetpack-protect' ),
						dateI18n( 'M j, Y', date )
				  )
				: __( 'Threat ignored', 'jetpack-protect' ) }
		</span>
	);

	const ScanHistoryDetails = ( { viewingHistory, detectedAt, fixedAt } ) => {
		if ( ! viewingHistory ) {
			return null;
		}

		return (
			<>
				{ detectedAt && (
					<Text className={ styles[ 'accordion-header-status' ] }>
						{ sprintf(
							/* translators: %s: First detected date */
							__( 'Threat found %s', 'jetpack-protect' ),
							dateI18n( 'M j, Y', detectedAt )
						) }
						<span className={ styles[ 'accordion-header-status-separator' ] }></span>
						<FixDetails date={ fixedAt || detectedAt } isFixed={ !! fixedAt } />
					</Text>
				) }
				<StatusBadge status={ fixedAt ? 'fixed' : 'ignored' } />
			</>
		);
	};

	const StatusBadge = ( { status } ) => (
		<div className={ `${ styles[ 'status-badge' ] } ${ styles[ status ] }` }>
			{ status === 'fixed' ? __( 'Fixed', 'jetpack-protect' ) : __( 'Ignored', 'jetpack-protect' ) }
		</div>
	);

	return (
		<div className={ styles[ 'accordion-item' ] }>
			<button className={ styles[ 'accordion-header' ] } onClick={ handleClick }>
				<div>
					<Text className={ styles[ 'accordion-header-label' ] } mb={ 1 }>
						<Icon icon={ icon } className={ styles[ 'accordion-header-label-icon' ] } />
						{ label }
					</Text>
					<Text
						className={ styles[ 'accordion-header-description' ] }
						variant={ open ? 'title-small' : 'body' }
					>
						{ title }
					</Text>
					<ScanHistoryDetails
						viewingHistory={ viewingScanHistory }
						detectedAt={ firstDetected }
						fixedAt={ fixedOn }
					/>
				</div>
				<div>
					<ThreatSeverityBadge severity={ severity } />
				</div>
				<div>
					{ fixable && (
						<>
							{ threatsAreFixing.indexOf( id ) >= 0 ? (
								<Spinner color="black" />
							) : (
								<Icon icon={ check } className={ styles[ 'icon-check' ] } size={ 28 } />
							) }
							{ isSmall && <span>{ __( 'Auto-fix', 'jetpack-protect' ) }</span> }
						</>
					) }
				</div>
				<div className={ styles[ 'accordion-header-button' ] }>
					<Icon icon={ open ? chevronUp : chevronDown } size={ 38 } />
				</div>
			</button>
			<div className={ bodyClassNames } aria-hidden={ open ? 'false' : 'true' }>
				{ children }
			</div>
		</div>
	);
};

const PaidAccordion = ( { children } ) => {
	const [ open, setOpen ] = useState();

	return (
		<PaidAccordionContext.Provider value={ { open, setOpen } }>
			<div className={ styles.accordion }>{ children }</div>
		</PaidAccordionContext.Provider>
	);
};

export default PaidAccordion;

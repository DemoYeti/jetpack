import { AdminSectionHero, Container, Col, H3, Text, Title } from '@automattic/jetpack-components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useCallback } from 'react';
import { useParams } from 'react-router-dom';
import AdminPage from '../../../components/admin-page';
import AlertSVGIcon from '../../../components/alert-icon';
import ProtectCheck from '../../../components/protect-check-icon';
import ScanFooter from '../../../components/scan-footer';
import ThreatsNavigation from '../../../components/threats-list/navigation';
import PaidList from '../../../components/threats-list/paid-list';
import useThreatsList from '../../../components/threats-list/use-threats-list';
import useAnalyticsTracks from '../../../hooks/use-analytics-tracks';
import useProtectData from '../../../hooks/use-protect-data';
import ScanSectionHeader from '../scan-section-header';
import StatusFilters from './status-filters';
import styles from './styles.module.scss';

const ScanHistoryRoute = () => {
	// Track page view.
	useAnalyticsTracks( { pageViewEventName: 'protect_scan_history' } );

	const { filter = 'all' } = useParams();
	const { numThreats, error, errorMessage, errorCode } = useProtectData( {
		sourceType: 'history',
	} );
	const { item, list, selected, setSelected } = useThreatsList( {
		source: 'history',
		status: filter,
	} );

	/**
	 * Get the title for the threats list based on the selected filters and the amount of threats.
	 */
	const getTitle = useCallback( () => {
		switch ( selected ) {
			case 'all':
				if ( list.length === 1 ) {
					switch ( filter ) {
						case 'fixed':
							return __( 'All fixed threats', 'jetpack-protect' );
						case 'ignored':
							return __(
								'All ignored threats',
								'jetpack-protect',
								/** dummy arg to avoid bad minification */ 0
							);
						default:
							return __( 'All threats', 'jetpack-protect' );
					}
				}
				switch ( filter ) {
					case 'fixed':
						return sprintf(
							/* translators: placeholder is the amount of fixed threats found on the site. */
							__( 'All %s fixed threats', 'jetpack-protect' ),
							list.length
						);
					case 'ignored':
						return sprintf(
							/* translators: placeholder is the amount of ignored threats found on the site. */
							__( 'All %s ignored threats', 'jetpack-protect' ),
							list.length
						);
					default:
						return sprintf(
							/* translators: placeholder is the amount of threats found on the site. */
							__( 'All %s threats', 'jetpack-protect' ),
							list.length
						);
				}
			case 'wordpress':
				switch ( filter ) {
					case 'fixed':
						return sprintf(
							/* translators: placeholder is the amount of fixed WordPress threats found on the site. */
							_n(
								'%1$s fixed WordPress threat',
								'%1$s fixed WordPress threats',
								list.length,
								'jetpack-protect'
							),
							list.length
						);
					case 'ignored':
						return sprintf(
							/* translators: placeholder is the amount of ignored WordPress threats found on the site. */
							_n(
								'%1$s ignored WordPress threat',
								'%1$s ignored WordPress threats',
								list.length,
								'jetpack-protect'
							),
							list.length
						);
					default:
						return sprintf(
							/* translators: placeholder is the amount of WordPress threats found on the site. */
							_n(
								'%1$s WordPress threat',
								'%1$s WordPress threats',
								list.length,
								'jetpack-protect'
							),
							list.length
						);
				}
			case 'files':
				switch ( filter ) {
					case 'fixed':
						return sprintf(
							/* translators: placeholder is the amount of fixed file threats found on the site. */
							_n(
								'%1$s fixed file threat',
								'%1$s fixed file threats',
								list.length,
								'jetpack-protect'
							),
							list.length
						);
					case 'ignored':
						return sprintf(
							/* translators: placeholder is the amount of ignored file threats found on the site. */
							_n(
								'%1$s ignored file threat',
								'%1$s ignored file threats',
								list.length,
								'jetpack-protect'
							),
							list.length
						);
					default:
						return sprintf(
							/* translators: placeholder is the amount of file threats found on the site. */
							_n( '%1$s file threat', '%1$s file threats', list.length, 'jetpack-protect' ),
							list.length
						);
				}
			case 'database':
				switch ( filter ) {
					case 'fixed':
						return sprintf(
							/* translators: placeholder is the amount of fixed database threats found on the site. */
							_n(
								'%1$s fixed database threat',
								'%1$s fixed database threats',
								list.length,
								'jetpack-protect'
							),
							list.length
						);
					case 'ignored':
						return sprintf(
							/* translators: placeholder is the amount of ignored database threats found on the site. */
							_n(
								'%1$s ignored database threat',
								'%1$s ignored database threats',
								list.length,
								'jetpack-protect'
							),
							list.length
						);
					default:
						return sprintf(
							/* translators: placeholder is the amount of database threats found on the site. */
							_n( '%1$s database threat', '%1$s database threats', list.length, 'jetpack-protect' ),
							list.length
						);
				}
			default:
				switch ( filter ) {
					case 'fixed':
						return sprintf(
							/* translators: Translates to "123 fixed threats in Example Plugin (1.2.3)" */
							_n(
								'%1$s fixed threat in %2$s %3$s',
								'%1$s fixed threats in %2$s %3$s',
								list.length,
								'jetpack-protect'
							),
							list.length,
							item?.name,
							item?.version
						);
					case 'ignored':
						return sprintf(
							/* translators: Translates to "123 ignored threats in Example Plugin (1.2.3)" */
							_n(
								'%1$s ignored threat in %2$s %3$s',
								'%1$s ignored threats in %2$s %3$s',
								list.length,
								'jetpack-protect'
							),
							list.length,
							item?.name,
							item?.version
						);
					default:
						return sprintf(
							/* translators: Translates to "123 threats in Example Plugin (1.2.3)" */
							_n(
								'%1$s threat in %2$s %3$s',
								'%1$s threats in %2$s %3$s',
								list.length,
								'jetpack-protect'
							),
							list.length,
							item?.name,
							item?.version
						);
				}
		}
	}, [ selected, list.length, filter, item?.name, item?.version ] );

	return (
		<AdminPage>
			<AdminSectionHero>
				<Container horizontalSpacing={ 3 } horizontalGap={ 4 }>
					<Col>
						<ScanSectionHeader
							subtitle={ __( 'Threat history', 'jetpack-protect' ) }
							title={ sprintf(
								/* translators: %s: Total number of threats  */
								__( '%1$s previously active %2$s', 'jetpack-protect' ),
								numThreats,
								numThreats === 1 ? 'threat' : 'threats'
							) }
						/>
					</Col>
					{ error ? (
						<Col>
							<AlertSVGIcon />
							<H3>
								{ errorMessage && errorCode
									? `${ errorMessage } (${ errorCode })`
									: __(
											"An error occurred loading your site's threat history.",
											'jetpack-protect'
									  ) }
							</H3>
							<Text>{ __( 'Please wait a moment and then try again.', 'jetpack-protect' ) }</Text>
						</Col>
					) : (
						<Col>
							<Container fluid horizontalSpacing={ 0 } horizontalGap={ 3 }>
								<Col lg={ 4 }>
									<ThreatsNavigation
										selected={ selected }
										onSelect={ setSelected }
										sourceType="history"
										statusFilter={ filter }
									/>
								</Col>
								<Col lg={ 8 }>
									{ list.length > 0 ? (
										<div>
											<div className={ styles[ 'list-header' ] }>
												<Title className={ styles[ 'list-title' ] }>{ getTitle() }</Title>
												<div className={ styles[ 'list-header__controls' ] }>
													<StatusFilters />
												</div>
											</div>
											<PaidList list={ list } />
										</div>
									) : (
										<div className={ styles.empty }>
											<ProtectCheck />
											<H3 weight="bold" mt={ 8 }>
												{ __( "Don't worry about a thing", 'jetpack-protect' ) }
											</H3>
											<Text>
												{ __(
													'There are no threats in your scan history for the selected filters.',
													'jetpack-protect'
												) }
											</Text>
										</div>
									) }
								</Col>
							</Container>
						</Col>
					) }
				</Container>
			</AdminSectionHero>
			<ScanFooter />
		</AdminPage>
	);
};

export default ScanHistoryRoute;

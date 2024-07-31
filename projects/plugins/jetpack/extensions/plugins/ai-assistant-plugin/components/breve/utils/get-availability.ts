import { getFeatureAvailability } from '../../../../../blocks/ai-assistant/lib/utils/get-feature-availability';

const blogId = parseInt( window?.Jetpack_Editor_Initial_State?.wpcomBlogId );

// Enable backend prompts for beta sites + 50% of production sites.
const isBreveAvailable =
	getFeatureAvailability( 'ai-proofread-breve' ) || [ 0, 2, 6, 7, 9 ].includes( blogId % 10 );

export default isBreveAvailable;

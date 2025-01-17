import commonjs from '@rollup/plugin-commonjs';
import json from '@rollup/plugin-json';
import resolve from '@rollup/plugin-node-resolve';
import terser from '@rollup/plugin-terser';
import typescript from '@rollup/plugin-typescript';
import nodePolyfills from 'rollup-plugin-polyfill-node';

const sharedPlugins = [
	resolve( {
		browser: true,
		preferBuiltins: false,
		modulesOnly: false,
	} ),
	typescript( {
		tsconfig: 'tsconfig.browser.json',
		sourceMap: true,
		inlineSources: false,
		declaration: false,
	} ),
	commonjs(),
	nodePolyfills(),
	json(),
];

export default {
	input: 'src/browser.ts',
	output: [
		{
			sourcemap: true,
			format: 'iife',
			name: 'CriticalCSSGenerator',
			file: 'build-browser/bundle.full.js',
		},
		{
			sourcemap: true,
			format: 'iife',
			name: 'CriticalCSSGenerator',
			file: 'build-browser/bundle.js',
			plugins: [ terser() ],
		},
	],
	plugins: sharedPlugins,
	preserveSymlinks: true,
	watch: {
		clearScreen: false,
	},
};

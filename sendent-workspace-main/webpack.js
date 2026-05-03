const webpack = require('webpack')

const webpackConfig = require('@nextcloud/webpack-vue-config')
const path = require('path')

webpackConfig.entry = {
	main: { import: path.join(__dirname, 'src', 'main.js'), filename: 'main.js' },
	admin: { import: path.join(__dirname, 'src', 'admin.js'), filename: 'admin.js' },
}

webpackConfig.module.rules.push({
	test: /\.svg$/i,
	type: 'asset/source',
})

// Additional alias for Vue pointing at esm-bundler (for Vue3)
webpackConfig.resolve = webpackConfig.resolve || {}
webpackConfig.resolve.alias = {
  ...(webpackConfig.resolve.alias || {}),
  vue: 'vue/dist/vue.esm-bundler.js',
}

// Define plugin for Vue feature flags
webpackConfig.plugins = webpackConfig.plugins || []
webpackConfig.plugins.push(
  new webpack.DefinePlugin({
    __VUE_OPTIONS_API__: JSON.stringify(true),
    __VUE_PROD_DEVTOOLS__: JSON.stringify(false),
  })
)

module.exports = webpackConfig

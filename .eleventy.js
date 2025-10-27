// imports for the various eleventy plugins (navigation & image)
const eleventyNavigationPlugin = require('@11ty/eleventy-navigation');
const { DateTime } = require('luxon');
const Image = require('@11ty/eleventy-img');
const path = require('path');

module.exports = function (eleventyConfig) {
  // allows the use of {% image... %} to create responsive, optimised images
  // CHANGE DEFAULT MEDIA QUERIES AND WIDTHS
  async function imageShortcode(
    src,
    alt,
    className = '',
    loading,
    sizes = '(max-width: 600px) 400px, 850px'
  ) {
    // create the metadata for an optimised image
    const metadata = await Image(`${src}`, {
      widths: [200, 400, 850, 1920, 2500],
      formats: ['webp', 'jpeg'],
      urlPath: '/images/',
      outputDir: './public/images',
      filenameFormat: function (id, src, width, format) {
        const extension = path.extname(src);
        const name = path.basename(src, extension);
        return `${name}-${width}w.${format}`;
      },
    });

    // get the smallest and biggest image for picture/image attributes
    const lowsrc = metadata.jpeg[0];
    const highsrc = metadata.jpeg[metadata.jpeg.length - 1];

    // when {% image ... %} is used, this is what's returned
    return `<picture class="${className}">
${Object.values(metadata)
  .map((imageFormat) => {
    return `  <source type="${imageFormat[0].sourceType}" srcset="${imageFormat
      .map((entry) => entry.srcset)
      .join(', ')}" sizes="${sizes}">`;
  })
  .join('\n')}
  <img
    src="${lowsrc.url}"
    width="${highsrc.width}"
    height="${highsrc.height}"
    alt="${alt}"
    loading="${loading}"
    decoding="async">
</picture>`;
  }

  // adds the navigation plugin for easy navs
  eleventyConfig.addPlugin(eleventyNavigationPlugin);

  // allows css, assets, robots.txt and CMS config files to be passed into /public
  eleventyConfig.addPassthroughCopy('./src/css/**/*.css');
  eleventyConfig.addPassthroughCopy('./src/assets');
  eleventyConfig.addPassthroughCopy('./src/_redirects');
  eleventyConfig.addPassthroughCopy({ './src/robots.txt': '/robots.txt' });
  eleventyConfig.addPassthroughCopy('src/images');
  eleventyConfig.addPassthroughCopy({ 'src/_data/products.json': 'products.json' });
  
  // Copy admin/ and api/ directories from root to public/
  eleventyConfig.addPassthroughCopy({ './admin': 'admin' });
  eleventyConfig.addPassthroughCopy({ './api': 'api' });
  eleventyConfig.addWatchTarget('src/_data/products.json');

  // Expose a dev flag to templates (true when running `eleventy --serve`)
  eleventyConfig.addGlobalData('isDev', process.env.ELEVENTY_RUN_MODE === 'serve');

  // open on npm start and watch CSS files for changes - doesn't trigger 11ty rebuild
  eleventyConfig.setBrowserSyncConfig({
    open: true,
    files: './public/css/**/*.css',
  });

  // allows the {% image %} shortcode to be used for optimised images (in webp if possible)
  eleventyConfig.addNunjucksAsyncShortcode('image', imageShortcode);

  // friendlier post date filter
  eleventyConfig.addFilter('postDate', (dateObj) => {
    return DateTime.fromJSDate(dateObj).toLocaleString(DateTime.DATE_MED);
  });

  return {
    dir: {
      input: 'src',
      includes: '_includes',
      layouts: '_layouts',
      output: 'public',
      // data: '_data' // (optional; default is "_data")
    },
    // allows .html files to contain nunjucks templating language
    htmlTemplateEngine: 'njk',
    markdownTemplateEngine: 'njk',
  };
};

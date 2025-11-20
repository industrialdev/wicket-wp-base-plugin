const postcssPrefixWrap = require('postcss-prefixwrap');

module.exports = {
  plugins: [
    (css, result) => {
      if (result.opts.from && /wicket[-_.]wrapped.*\\.css$/i.test(result.opts.from)) {
        return postcssPrefixWrap(".wicket-base-plugin").Once(css, { result });
      }
    },
    require("tailwindcss"),
    require("autoprefixer"),
  ],
};

## Package development
  - Document every new or changed feature in both places, in the same change:
    - `site/index.html`, the full documentation published on GitHub Pages: add or update its section. The left menu is built from the `h2`/`h3` headings; give a heading a `data-nav` attribute for a shorter menu label.
    - `README.md`: keep it concise. At most a line in Features or a bullet in the design guidance, linking to the section in the site. Detail belongs in the site, never in the README.

## Commands
  - Test: `composer test`
  - Linting: `composer lint` (`composer format` to fix)
  - Static analysis: `composer analyse`

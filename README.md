# Etiketter

A WordPress + WooCommerce website where customers can design and order custom stickers.

### Localization

To generate .pot file

```
composer make-pot
```

Then everything can be translated the usual WordPress way.

### TODOs

[X] - Make stickers editable
[X] - Link cart item meta to edit page
[X] - Store cart item meta as order item meta?
[X] - Link order item meta to sticker details page
[ ] - Make order details in My Account more beautiful?
[ ] - Add support for icon uploads

## Features

### End-User Features

**Sticker Designer**
- Select sticker size from predefined options (shown with dimensions in mm and shape)
- Select sticker color from predefined swatches
- Select a symbol/icon from a predefined image library
- Add custom text across multiple lines (max lines and characters per line depend on the chosen size)
- Per text line: choose font family (sans-serif or serif), apply italic, apply bold, set alignment (left/center/right)
- Live sticker preview that updates instantly as any option changes
- Confirmation dialog when starting a new sticker to prevent accidental data loss

**Cart & Checkout**
- "Save and add to cart" adds the sticker product directly to the WooCommerce cart in one action
- The mini-cart opens automatically after adding
- Sticker thumbnail is shown on the cart item
- "Edit sticker" link in the cart takes the customer back to their design
- Sticker thumbnail is shown on the order confirmation and in My Account order history

**Editing an existing sticker**
- Load a sticker design via a URL (e.g. from the cart edit link) to modify it
- "Save" — update the existing design in place
- "Save as new and add to cart" — keep the original and create a new variant

### Webmaster / WP Admin Features

**Sticker list (custom post type)**
- All customer-created stickers are stored and listed under a "Sticker" menu in WP Admin
- The list shows a thumbnail preview of each sticker
- The list shows which order each sticker is linked to (with a clickable link to the order)

**Sticker edit page**
- Full SVG preview of the sticker design
- "Download SVG" button to export the sticker file

**Orders (WooCommerce)**
- Sticker thumbnails are shown on order line items in the admin order view
- The sticker UUID is shown as a clickable link that jumps to the sticker's edit page

**Settings (ACF options page)**
- Configure available sticker sizes (width, height in mm, shape, linked WooCommerce product)
- Configure available colors (name + hex color)
- Configure available symbols (image + description)

**Automatic maintenance**
- Sticker designs that are older than 7 days and not linked to any order are automatically deleted daily

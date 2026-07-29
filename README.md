# Pricing Manager

Production-focused WordPress plugin for WooCommerce stores that sell digital or virtual variable products.

## Installation

1. Copy the plugin folder into `wp-content/plugins/`.
2. Activate **Pricing manager** from WordPress Admin > Plugins.
3. Make sure WooCommerce is active.
4. Configure variation pricing from the WooCommerce product variation editor.

## Assumptions

- WooCommerce is installed and active.
- Products are variable products with digital or virtual variations.
- Customers should see prices only in EGP.
- Base prices are stored internally in USD.
- The USD to EGP exchange rate can be entered in WooCommerce > Settings > General > Currency options.
- If the exchange rate field is empty, the plugin tries to use the online USD to EGP rate.

## Architecture

The plugin uses small OOP classes with separated responsibilities:

- `Pricing_Manager`: boots the plugin and registers modules.
- `Product_Meta_Repository`: reads and writes variation pricing metadata.
- `Settings_Repository`: reads and writes exchange-rate settings.
- `Exchange_Rate_Provider`: fetches and caches online exchange rates.
- `Price_Calculator`: calculates EGP prices from USD base price, margin, and exchange rate.
- `Price_Filter`: applies calculated prices to WooCommerce customer-facing prices.
- `Variation_Pricing_Admin`: adds Base Price and Profit Margin fields to variations.
- `Order_Pricing_Metadata`: stores immutable pricing snapshots on order line items.
- `Digital_Processing_Statuses`: adds independent payment and fulfillment statuses to orders.
- `Admin_Dashboard` and `Dashboard_Repository`: provide operational reporting with filters and pagination.
- `Pricing_Error_Handler`: logs pricing issues and shows safe admin notices.

## Limitations

- Online exchange-rate fetching depends on the external API being available.
- If no valid exchange rate exists, calculated pricing is skipped safely.
- Existing orders only contain pricing snapshots if they were created after the order metadata feature was active.
- Dashboard results depend on saved order item pricing metadata.
- The dashboard is optimized for paginated reads, but very large stores may still need database indexes tuned for their hosting environment.

## Review Checklist

- Create a variable digital product and set Base Price (USD) and Profit Margin (%).
- Confirm product and variation prices display in EGP.
- Place an order and confirm pricing metadata is saved on the order item.
- Edit Payment Status and Fulfillment Status from the order screen.
- Open WooCommerce > Pricing Dashboard and test filters, pagination, and Screen Options.

# Changelog

## [[v1.0.0]](https://github.com/The-Courier-Guy/TCG_Common/releases/tag/v1.0.0)

### Breaking

- Rebranded package from `pudo/pudo-common` to `tcg/tcg-common`.
- Changed root namespace from `Pudo\\Common\\` to `Tcg\\Common\\`.
- Moved locker classes into `src/Lockers/*` with updated namespaces.
- Removed mass-based calculations in packing logic. All items are dimensioned by default.

### Added

- Added `Tcg\\Common\\Models\\Product` standardised model for product mass/value and dimension handling.
- Added `Tcg\\Common\\Packing\\Controllers\\CommonPackingController` for box fitting and item partitioning.
- Added `Tcg\\Common\\Packing\\Controllers\\ShipLogicParcelController` for parcel calculation workflows.
- Added Shopify product services:
  - `Tcg\\Common\\Shopify\\Services\\Product\\BulkProductUpdate`
  - `Tcg\\Common\\Shopify\\Services\\Product\\ShopifyProductService`
- Unit tests for standard items and packing logic.
- Extensive instructions in README.md for developers using the library.

### Changed

- Updated project metadata and docs from PUDO to TCG naming (`README.md`, `composer.json`, release links).
- Updated Composer autoload mapping to `Tcg\\Common\\`.
- Relaxed PHP requirement from `^8.0` to `>=8.0`.
- Moved the Shopify product services from the individual apps into the common package for shared use.

### Improved
- Refactored codebase for improved structure, maintainability and reusability.
- Added handling for container (hollow) products in packing logic.
- Packing logic now supports item mass throughout i.e it is now 4-dimensional and not just 3-dimensional.

## [[v0.5.2]](https://github.com/The-Courier-Guy/PUDO_Common/releases/tag/v0.5.2)

### Added

- Additional TCG Locker parcel type rate options for P2P, P2L, P2K, P2D, K2P, D2P, and L2P service codes across supported size variants.

### Changed

- Renamed existing PUDO locker rate option labels to TCG Locker branding for consistent display.

### Fixed

- Parcel types not showing correctly in shipping rate options.

## [[v0.5.1]](https://github.com/The-Courier-Guy/TCG_Common/releases/tag/v0.5.1)

### Fixed

- Issue where company name was not being sent.

## [[v0.5.0]](https://github.com/The-Courier-Guy/TCG_Common/releases/tag/v0.5.0)

### Added

- Introduce new Shipping Service Class.

## [[v0.4.0]](https://github.com/The-Courier-Guy/TCG_Common/releases/tag/v0.4.0)

### Added

- Introduced new 'Print Label/Sticker' endpoint.

## [[v0.3.0]](https://github.com/The-Courier-Guy/TCG_Common/releases/tag/v0.3.0)

## Added

- Switch from **/api/v1/locker-rates** and **/api/v1/rates** to **/api/v1/locker-rates-new**.
- Add ApiRequestBuilder class.

## [[v0.2.0]](https://github.com/The-Courier-Guy/TCG_Common/releases/tag/v0.2.0)

## Added

- Introduce API URL Property.

## [[v0.1.0]](https://github.com/The-Courier-Guy/TCG_Common/releases/tag/v0.1.0)

- Initial release.

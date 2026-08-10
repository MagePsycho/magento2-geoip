<div align="center">

# Magento 2 GeoIP
![Magento 2 GeoIP](https://i.imgur.com/d8QEHRb.png)

</div>

<div align="center">

[![Packagist Version](https://img.shields.io/github/v/tag/MagePsycho/magento2-geoip?logo=packagist&sort=semver&label=packagist&style=for-the-badge)](https://packagist.org/packages/magepsycho/magento2-geoip)
[![Packagist Downloads](https://img.shields.io/packagist/dt/magepsycho/magento2-geoip.svg?logo=packagist&style=for-the-badge)](https://packagist.org/packages/magepsycho/magento2-geoip/stats)
![Supported Magento Versions](https://img.shields.io/badge/magento-%202.4-brightgreen.svg?logo=magento&longCache=true&style=for-the-badge)
![License](https://img.shields.io/badge/license-OSL--3.0-green?color=%23234&style=for-the-badge)

</div>

## Overview

**Magento 2 GeoIP** is a foundation module: it wraps MaxMind GeoLite2 (City + ASN) behind a small, stable PHP interface so other extensions can ask "where is this IP from" without each one shipping its own reader, its own download logic and its own error handling.

It has no storefront output and no admin grid of its own. What it provides is one injectable service, one value object, and the plumbing that keeps the `.mmdb` files on disk and up to date.

Built to be consumed by other MagePsycho extensions — [SalesOrderGrid](https://github.com/MagePsycho/magento2-sales-order-grid) (the order-grid IP popup), country-at-signup on customer registration, country-based cart rules in SalesPromotionPro — and by your own code just as well.

## Key Features
* One interface — `IpLookupInterface::lookup()` — returning a typed result object
* **Never throws.** `null` on failure, logged at warning level, so consumer code stays branch-free
* Country, region, city, postal code, latitude/longitude, timezone, ASN and AS organisation from a single call
* Country **flag emoji** and Magento-localised country names
* Private / reserved addresses recognised and short-circuited before touching the database
* Two setup paths: auto-download with MaxMind credentials, or drop the files in yourself
* CLI refresh (`magepsycho:geoip:update`), an admin **Update Now** button, and a configurable cron expression
* Readers opened lazily and reused for the lifetime of the request
* **Forced IP** setting for end-to-end testing without faking order data

## Feature Highlights

### The Consumer API

```php
use MagePsycho\GeoIp\Api\IpLookupInterface;

class YourConsumer
{
    public function __construct(private IpLookupInterface $lookup) {}

    public function tellMeAbout(string $ip): void
    {
        $result = $this->lookup->lookup($ip);
        if ($result === null) {
            // unresolved, or DB not installed — handle gracefully
            return;
        }

        echo $result->getCountryFlagEmoji() . ' ' . $result->getCountryName();
        echo $result->getCity();
        echo 'AS' . $result->getAsn() . ' ' . $result->getAsnOrganization();
    }
}
```

`IpLookupResultInterface` exposes `getIp()`, `getCountryCode()`, `getCountryName()`, `getCountryFlagEmoji()`, `getRegion()`, `getCity()`, `getPostalCode()`, `getLatitude()`, `getLongitude()`, `getTimezone()`, `getAsn()`, `getAsnOrganization()`, `getIsPrivate()` and `toArray()`. Every geographic getter is nullable — GeoLite2 resolves country far more often than city.

### The Behavioural Contract

`lookup()` never throws. It resolves to one of four outcomes:

| Situation | Returns |
|---|---|
| Address resolved | `IpLookupResult` with whatever GeoLite2 knows |
| Private / reserved address | `IpLookupResult::forPrivateIp()` — `getIsPrivate()` is `true` |
| Database not installed | `null`, plus a single warning in the log |
| Address not in the database | `null` |

Private-range detection is `filter_var()` with `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`, which covers `10/8`, `172.16/12`, `192.168/16`, IPv6 ULA `fc00::/7`, loopback and the reserved blocks — so a LAN address never costs a database read.

`isPrivate()` is exposed separately, for callers that want to skip the lookup entirely.

### Two Setup Paths

You only need one of these.

**A. Auto-download via MaxMind credentials**

1. Sign up free at <https://www.maxmind.com/en/geolite2/signup>
2. Generate an Account ID + License Key in your MaxMind account
3. Enter both under **Stores > Configuration > MagePsycho > GeoIP > MaxMind GeoLite2**
4. Run `bin/magento magepsycho:geoip:update`, or press **Update Now** in admin
5. Leave the cron expression at `0 3 * * 1` — MaxMind publishes new builds twice a week

**B. Manual file drop**

For hosts with outbound firewall restrictions, or if you would rather manage the files yourself:

1. Download `GeoLite2-City.mmdb` and `GeoLite2-ASN.mmdb` from MaxMind
2. Drop them into `var/maxmind/`
3. Done — the credential fields can stay empty

*The downloader fetches each edition's `tar.gz` over HTTPS authenticated by the License Key, streams it to `var/maxmind/_extract/`, untars it, and moves the `.mmdb` into place. A failed download leaves the previous database untouched.*

### Forced IP For Testing

**Stores > Configuration > MagePsycho > GeoIP > General > Forced IP for Testing**

When set, every lookup is overridden with that address. Lets you exercise a country-specific code path end to end without fabricating orders or spoofing headers.

*Leave it empty outside development — it silently makes every visitor look like they are in one place.*

### What Is Not Included

* **Proxy / VPN / Tor flags** — those need MaxMind's paid GeoIP2 Anomaly Score product, not the free GeoLite2 series
* **Localised city names** — MaxMind returns English city and region names. Country names *are* localised, via Magento's `TranslatedLists`

## 🛠️ Installation

### 1 Using Composer (Preferred)
```
composer require magepsycho/magento2-geoip
```

### 2 Using Modman
```
modman init
modman clone git@github.com:MagePsycho/magento2-geoip.git
```

### 3 Using Zip File
* Download the [Extension Zip File](https://github.com/MagePsycho/magento2-geoip/archive/master.zip)
* Extract & upload the files to `/path/to/magento2/app/code/MagePsycho/GeoIp/`

*Zip and modman installs must also provide the two Composer dependencies below; `composer require` handles them for you.*

After installation by either means, activate the extension with following steps

1. Enable the module
```
php bin/magento module:enable MagePsycho_GeoIp --clear-static-content
php bin/magento setup:upgrade
```
2. Flush the store cache
```
php bin/magento cache:flush
```
3. Install the databases — either path from [Two Setup Paths](#two-setup-paths)
```
php bin/magento magepsycho:geoip:update
```

The extension creates no tables of its own. The databases live in `var/maxmind/` as files.

### Requirements

| Requirement | Version |
|---|---|
| PHP | 7.4 – 8.5 |
| `geoip2/geoip2` | `~3.0` — MaxMind PHP reader |
| `splitbrain/php-archive` | `^1.4.0` — tar.gz extraction for the auto-downloader |

## Configuration

**Stores > Configuration > MagePsycho > GeoIP**

### General

| Setting | Default | Purpose |
|---|---|---|
| Enabled | Yes | Gates the scheduled auto-update. **Does not** currently gate `lookup()` |
| Forced IP for Testing | *(empty)* | Overrides every lookup with this address |

### MaxMind GeoLite2

| Setting | Default | Purpose |
|---|---|---|
| Account ID | *(empty)* | MaxMind account, for auto-download |
| License Key | *(empty)* | MaxMind license key, stored encrypted |
| Last Database Import | — | Read-only timestamp of the installed database |
| Update Now | — | Downloads both editions immediately |
| Auto-update Cron Expression | `0 3 * * 1` | Weekly refresh; drives `magepsycho_geoip_update_database` |

Admin access is gated by the `MagePsycho_GeoIp::config` ACL resource.

## Developer Notes

### Depend on the interface, not the model

```xml
<type name="Your\Module\Model\Whatever">
    <arguments>
        <argument name="lookup" xsi:type="object">MagePsycho\GeoIp\Api\IpLookupInterface</argument>
    </arguments>
</type>
```

`IpLookupInterface`, `IpLookupResultInterface` and `DatabaseManagerInterface` are the supported surface. `Model\IpLookup` and `Model\DatabaseManager` are implementation detail and may change.

### Readers are per-request

`Model\IpLookup` opens the City and ASN readers lazily and holds them for the lifetime of the request — the first `lookup()` pays for opening the file, subsequent calls in the same request do not. Nothing is cached *across* requests, so a database refresh takes effect immediately with no cache flush.

For a grid rendering hundreds of rows, prefer resolving on demand (a click, an AJAX call) over looking up every row on page load.

### Checking availability before you rely on it

`DatabaseManagerInterface` exposes `isInstalled()`, `getInstalledAt()`, `getCityDatabasePath()`, `getAsnDatabasePath()` and `download()`. Use `isInstalled()` when you want to render a "geolocation unavailable" state rather than an empty result — `lookup()` alone cannot tell you the difference between "no database" and "IP not found".

### Scheduled refresh

`etc/crontab.xml` registers `magepsycho_geoip_update_database` against `MagePsycho\GeoIp\Cron\UpdateDatabase`, with its schedule read from `magepsycho_geoip/maxmind/cron_expr`. Changing the expression in admin changes the schedule; no code change needed.

## Changelog

**Version 1.0.0**

* Initial Release.

## Authors
- Raj KB [![Twitter Follow](https://img.shields.io/twitter/follow/rajkbnp.svg?style=social)](https://twitter.com/rajkbnp)

## Contributors

![Contributors](https://contrib.rocks/image?repo=magepsycho/magento2-geoip)

## To Contribute
Any contribution to the development of `Magento 2 GeoIP` is highly welcome.  
The best possibility to provide any code is to open a [pull request on GitHub](https://github.com/MagePsycho/magento2-geoip/pulls).

## Need Support?
If you encounter any problems or bugs, please create an issue on [GitHub](https://github.com/MagePsycho/magento2-geoip/issues).

Please [visit our store](https://www.magepsycho.com/extensions/magento-2.html) for more FREE / paid extensions OR [contact us](https://magepsycho.com/contact) for customization / development services.

---

*This module is a dependency of other MagePsycho extensions and is not a standalone feature — it does nothing visible on its own.*

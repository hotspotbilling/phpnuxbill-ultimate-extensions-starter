# Device driver skeleton

A driver connects PHPNuxBill plans and customers to a piece of network equipment (a router, a RADIUS server, a captive
portal, a custom API). It is one class. Folder style puts it next to its manifest:

```
MyDevice/
├── device.json     manifest (name, slug = class name, category, requirements)
└── MyDevice.php    class MyDevice { ... }
```

## Make it yours

```bash
php skeletons/build.php new device AcmeRouter "Acme Router"
```

The slug is the **class name** and must match the file name (`AcmeRouter/AcmeRouter.php`, `class AcmeRouter`). The billing
engine calls `(new $plan['device'])->method(...)` with no constructor arguments.

## The methods

| Method | Called when |
|---|---|
| `description()` | Settings > Devices and the Marketplace list the driver |
| `add_customer($customer, $plan)` | a customer is activated / buys a plan: create or enable the account |
| `remove_customer($customer, $plan)` | the plan expires or the customer is removed |
| `change_username($plan, $from, $to)` | the username changes |
| `add_plan($plan)` / `update_plan($old_name, $plan)` / `remove_plan($plan)` | a plan is created / edited / deleted |
| `online_customer($customer, $router_name)` | the UI asks if the customer is online (return true/false) |
| `connect_customer($customer, $ip, $mac_address, $router_name)` | hotspot login |
| `disconnect_customer($customer, $router_name)` | hotspot logout |
| `add_pool` / `update_pool` / `remove_pool` | optional, for plan types that use IP pools (PPPOE) |

`$router_name` is the device name from Network > Routers; `info($name)` in the skeleton fetches that row (IP, username,
password). Leave unused methods empty rather than deleting them: the engine calls them all.

`category` in `device.json` (`Mikrotik`, `Radius`, `Voucher`, `Other`) groups the driver in the Marketplace.

## What `MyDevice.php` demonstrates

| # | Section | APIs |
|---|---|---|
| 1 | Licensing (off by default) | `register_license(..., 'device')`, `"license_provider": true` in `device.json` |
| 2 | The methods | `add_customer`, `remove_customer`, the optional `sync_customer`, plan and hotspot methods |
| 3 | Talking to a device | `info()` for the `tbl_routers` row, `getClient()` with the RouterOS client (`PEAR2\Net\RouterOS`), Demo mode, `Http::postJsonData()` for HTTP equipment |

The header of the file lists the helpers the core already gives you.

## Licensing a paid driver

Set `"license_provider": true` in `device.json` and `$mydevice_licensed = true` in the file. The core then includes the file (once) to
find your `register_license()`, shows a **License** button on the driver's Marketplace card, and does not let plans use the driver
until `mydevice_license_status()` answers `valid`. Do not set `requires_license` as well. Only a folder driver can do this.

## Using the driver

1. Install it (Marketplace > Device Drivers > Upload, or copy the folder to `system/devices/`).
2. In Services, edit a plan and pick **My Device** in its *Device* field. New plans can choose it too.
3. A driver that plans still use cannot be uninstalled; move those plans to another device first.

## Rules

* Never `exit` or print from a driver; throw only when the request should fail.
* Keep calls to the device short; they run inside the customer's request.
* Core drivers (`MikrotikHotspot`, `MikrotikPppoe`, `MikrotikStatic`, `MikrotikVpn`, `Radius*`, `Voucher`, `Dummy`) stay as
  single files and cannot be replaced by an upload.
* The folder and the flat `MyDevice.php` layouts are both resolved by `DeviceLoader`.

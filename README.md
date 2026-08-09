<p align="center">
  <img src="https://centralcorp.github.io/img/panel.png" alt="CentralCorp Minecraft Launcher Panel logo" width="720">
</p>

# CentralCorp Panel

CentralCorp Panel is the self-hosted web administration panel for CentralCorp Minecraft Launcher. It manages launcher configuration, Azuriom integration, Minecraft versions and loaders, game files, optional mods, access controls and interface settings from a Laravel application.

[![Latest release](https://img.shields.io/github/v/release/CentralCorp/centralpanel-v2?label=release)](https://github.com/CentralCorp/centralpanel-v2/releases/latest)
[![CentralCorp CI](https://github.com/CentralCorp/centralpanel-v2/actions/workflows/tests.yml/badge.svg)](https://github.com/CentralCorp/centralpanel-v2/actions/workflows/tests.yml)
[![License: CC0 1.0](https://img.shields.io/badge/license-CC0%201.0-3451b2.svg)](LICENSE)
[![Documentation](https://img.shields.io/badge/docs-centralcorp.github.io-0ea5e9.svg)](https://centralcorp.github.io/)

**[Documentation](https://centralcorp.github.io/)** · **[Launcher](https://github.com/CentralCorp/CentralCorp-Launcher)** · **[Installer](https://github.com/CentralCorp/Installer)** · **[Panel guide](https://centralcorp.github.io/en/minecraft-launcher-panel)**

## 🖥️ Minecraft Launcher Panel

The panel stores launcher settings in its database and provides public endpoints consumed by CentralCorp Launcher. Administrators use authenticated web routes, while the launcher retrieves its configuration, file manifest and optional mod metadata.

This separation makes CentralCorp a self-hosted **Minecraft Launcher Panel**: the player uses the desktop launcher, and the server team manages it through the web panel.

![CentralCorp Minecraft Launcher Panel configuration dashboard](https://centralcorp.github.io/img/config.png)

## ✨ Features

- Azuriom URL and API-key configuration;
- synchronization of Azuriom servers, roles and users;
- default Minecraft server and server icon management;
- Minecraft version and Forge, Fabric, LegacyFabric, NeoForge or Quilt loader configuration;
- file manager and SHA-1 file manifest for launcher synchronization;
- ignored folders for file verification;
- optional mod names, descriptions, recommendations and images;
- launcher maintenance mode and user/role access lists;
- game directory, embedded Java, memory and verification settings;
- launcher accent color, alerts, video, splash text and role backgrounds;
- Discord Rich Presence configuration;
- settings import/export, administrator accounts and release-based panel updates.

## 🖼️ Preview

| File management | Guided panel setup |
| --- | --- |
| ![File manager in CentralCorp Minecraft Launcher Panel](https://centralcorp.github.io/img/fileman.png) | ![CentralCorp Panel guided installation screen](https://centralcorp.github.io/img/admincr.png) |

## Launcher management

CentralCorp Launcher reads the panel configuration from `/utils/api`, distributed files from `/data`, and optional mod information from `/utils/mods`. The panel's administrative interface controls the values returned by those endpoints.

See the [panel configuration guide](https://centralcorp.github.io/en/install/step4) for the documented file layout and launcher test flow.

## Azuriom integration

The standalone panel requires a configured Azuriom website URL and API key. It uses the API-extender endpoints to retrieve server, role and user data. Launcher authentication also relies on Azuriom.

The launcher is intended for Azuriom-connected offline-mode Minecraft server infrastructure; Microsoft online-mode servers are not supported.

## Forge and Fabric

Administrators select a Minecraft version and a supported loader in the panel. The current configuration lists Forge, Fabric, LegacyFabric, NeoForge and Quilt; the launcher receives the saved values through the public configuration endpoint.

## Files and optional mods

The file manager maintains launcher data and generates the SHA-1 manifest used for verification. Optional mod records provide names, descriptions, images and recommendation status so players can choose supported optional content in the launcher.

## Installation

CentralCorp Panel requires PHP 8.2 or newer, a supported database, the PHP extensions checked by the installer, writable storage and a correctly configured web server.

The recommended deployment path uses [CentralCorp Installer](https://github.com/CentralCorp/Installer/releases/latest). Follow the [panel installation guide](https://centralcorp.github.io/en/install/step3) for hosting and database steps.

For repository development, inspect `composer.json` and `package.json` before running the Laravel and Vite workflows. Frontend assets are built with:

```bash
npm install
npm run build
```

PHP dependencies and application tests use Composer and Artisan.

## CentralCorp ecosystem

- [Website and documentation](https://github.com/CentralCorp/centralcorp.github.io) — product pages and installation guides
- [CentralCorp Launcher](https://github.com/CentralCorp/CentralCorp-Launcher) — customizable player application
- [CentralCorp Panel](https://github.com/CentralCorp/centralpanel-v2) — this self-hosted administration panel
- [CentralCorp Installer](https://github.com/CentralCorp/Installer) — hosting checks and panel deployment

## License

This repository's `LICENSE` file applies [CC0 1.0 Universal](LICENSE). It dedicates the covered work to the public domain to the extent legally possible, with a permissive fallback license. Trademark, patent and third-party rights are not granted by CC0.

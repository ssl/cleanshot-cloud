
# cleanshot-cloud

Self-hosted CleanShot X cloud service to upload screenshots to your own hosting.

If you bought the CleanShot X software and want to upload screenshots to your own cloud / other host, there is no way of setting this up inside the CleanShot X software. This repo fixes that.

Uses [aapje.php](https://github.com/ssl/aapje.php) to recreate the CleanShot API.

## Usage

This repository holds multiple branches, with different implementations of the CleanShot API.

Make a visit to the branches and read the README.md there.

| Branch | Description | Link |
|--------|-------------|------|
| `selfhostdb` | Uses SQL database and uploads images to image/ folder | [selfhostdb branch](https://github.com/ssl/cleanshot-cloud/tree/selfhostdb) |
| `imgur` | Uses Imgur API without database | [imgur branch](https://github.com/ssl/cleanshot-cloud/tree/imgur) |

## Repository status

All branches are tested and working. The framework to replicate the CleanShot API is available and can be changed to your likings, for example to upload to AWS instead.

As of end 2025, this repository is archived and will no longer receive updates, as they are not needed.

# cleanshot-cloud

Self-hosted CleanShot X cloud service to upload screenshots to your own hosting.

If you bought the CleanShot X software and want to upload screenshots to your own cloud, there is no way of setting this up inside the CleanShot X software. This repo fixes that.

Uses [aapje.php](https://github.com/ssl/aapje.php) to recreate the CleanShot API.

## Usage

**Setup cleanshot-cloud on your webhost**
1. Clone this repository and upload all files on a webhost
2. Make sure all traffic route through index.php (this repo work out-of-the-box on Apache with the `.htaccess` file)
3. Rename or copy `.env.example` to `.env` and fill in your empty database details
4. Create the `uploads` table (by uploading) from the [db.sql](db.sql) file.
5. Make sure you can access your new API with HTTPS enabled

---
**Proxy CleanShot API through cleanshot-cloud**

In order to upload your screenshots to your own hosting, you need to replace the cleanshot API with your own API.

One way of doing this is with Surge software:
1. Make sure `api.cleanshot.cloud` is added to MitM hostnames inside HTTPS decryption
2. Create a URL rewrite (modify header) that rewrites the cleanshot api host to your just created host.
```
[URL Rewrite]
^https:\/\/api\.cleanshot\.cloud https://myapi.example.com header
```

---
**Use CleanShot X like you would always do**

If already logged in to CleanShot X, click logout and re-login. The login flow will now go through your own API, allowing uploading screenshots to your cloud. After login you should see the Self-hosted cloud plan inside your CleanShot X app. Start making a screenshot and click upload in the far right!

Any user data can be modified inside the `user.json` file. Other things like slug generation can be changed inside `index.php`. This repo offers the very basics needed to upload the screenshots to your own cloud. Any changes to the `index.php` could be made to, for example, upload to AWS instead.
# Gallery site: phase 1 setup

## 1. Cloudflare R2 (photo storage)
1. Create a Cloudflare account, open **R2**, and add a payment card (R2 requires one even for the free 10 GB).
2. Create a bucket, for example `gallery-photos`. Keep it private (the default).
3. In the bucket's **Settings > CORS policy**, paste this, with your own domain:

```json
[{"AllowedOrigins": ["https://yourdomain.com"],
  "AllowedMethods": ["GET", "PUT"],
  "AllowedHeaders": ["*"]}]
```

4. In R2, choose **Manage API tokens > Create token** with **Object Read & Write** permission for that bucket.
   Note the **Access Key ID**, the **Secret Access Key**, and your **Account ID**.

## 2. Hostinger
1. In hPanel, open **Databases > MySQL** and create a database and a user. Note the name, user and password.
2. Open **File Manager**, go to `public_html`, and upload every file from this folder, including `.htaccess`.
3. Make sure the domain has SSL (https) switched on.

## 3. Install
1. Visit `https://yourdomain.com/setup.php`.
2. Fill in the site name, an admin password, the database details and the R2 details, then press **Install**.
3. You land on `https://yourdomain.com/admin.php`. Log in and create your first collection.

## Daily use
- **Admin:** `/admin.php`
- **Client gallery link:** shown at the top of each collection, in the form `/g/collection-name`
- **Homepage:** `/` lists published galleries that are not hidden
- **Forgot the admin password:** delete `config.php` in the File Manager and run `setup.php` again with the same database and R2 details. Your galleries and photos are kept.

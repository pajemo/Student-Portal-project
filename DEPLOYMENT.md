# Go-Live Checklist & Deployment Guide

## Production Readiness Status ✅

This portal is now configured for live deployment with the following production features in place:

### SEO & Search Engine Optimization
- ✅ **Sitemaps**: `sitemap.xml` (page index) and `image-sitemap.xml` (image index)
- ✅ **Robots.txt**: Search engine crawler instructions configured
- ✅ **Structured Data**: Schema.org JSON-LD markup for Organization and Web Application
- ✅ **Meta Tags**: Description, keywords, Open Graph tags for social sharing
- ✅ **Dynamic URLs**: All internal links use environment-agnostic helpers for subfolder/domain portability

### Deployment Files Present
- `robots.txt` - Search engine instructions
- `sitemap.xml` - Page index for crawlers
- `image-sitemap.xml` - Image index for crawlers
- `site.webmanifest` - PWA manifest with relative paths
- `logo-light.png` - Favicon asset (used as favicon and app icon)
- `aim.png` - Academy branding logo (now in public/assets)
- `favicon` links in all pages

### What You Need to Do Before Going Live

#### 1. **Google Search Console Verification**
Replace the empty `google-site-verification` meta tag content:
```html
<meta name="google-site-verification" content="">
```

Steps:
1. Go to [Google Search Console](https://search.google.com/search-console)
2. Add your domain
3. Choose HTML tag verification method
4. Copy the `content` attribute value
5. Update the meta tag in both:
   - `templates/layout-student.php`
   - `templates/layout-admin.php`
6. Submit sitemap and image-sitemap URLs in Search Console

#### 2. **Domain Configuration**
- Update `APP_BASE_PATH` environment variable or allow auto-detection
- Ensure all public URLs use relative paths (already done)
- Update external links if needed (helpdesk.knust.edu.gh, aim.knust.edu.gh, etc.)

#### 3. **SSL/HTTPS**
- Ensure domain is served over HTTPS
- Update any http:// external links to https://

#### 4. **Security Headers** (Optional but Recommended)
Add to your web server configuration:
```
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
```

#### 5. **Database & Environment**
- Finalize `.env` with production credentials
- Ensure database backups are automated
- Verify PDO error handling is production-safe (no debug output)

#### 6. **Analytics** (Optional)
If you want usage tracking, add Google Analytics or similar:
- Add tracking ID to `site.webmanifest` or layout
- Or use UA-XXXX-X format in a meta/script tag

#### 7. **Content Verification**
- Test login flow for both student and admin users
- Verify all dynamic links work (no hardcoded /admin/public paths remain)
- Check footer copyright year (currently 2026)
- Verify email/phone links work if present

### Files Modified for Production
- `src/bootstrap.php` - Added dynamic URL helpers
- `public/robots.txt` - Configured with sitemaps
- `public/sitemap.xml`, `image-sitemap.xml` - Created
- `public/site.webmanifest` - PWA manifest with relative paths
- `templates/layout-student.php`, `layout-admin.php` - Added SEO meta and schema
- All public PHP pages - Converted to dynamic URL helpers
- `public/login.php` - Updated image paths to assets

### Testing Production URLs
Before deploying to live domain, test at these localhost URLs:

**Current Structure (public folder as webroot):**
1. `http://localhost/admin/public/` – Homepage/index
2. `http://localhost/admin/public/login.php` – Student login
3. `http://localhost/admin/public/admin/login.php` – Admin login

**Subfolder Deployment Test (recommended for live preview):**
To simulate a live domain subfolder deployment, create an Apache alias:
1. Edit `httpd-vhosts.conf` or `httpd.conf`
2. Add under `<Directory>` section:
   ```apache
   Alias /results-portal "C:/xampp/htdocs/admin/public"
   <Directory "C:/xampp/htdocs/admin/public">
       AllowOverride All
       Require all granted
   </Directory>
   ```
3. Restart Apache: `services.msc` → Apache24 → Restart
4. Test at: `http://localhost/results-portal/`

**Deployment Checklist:**
- ✅ All links work at both `http://localhost/admin/public` and `http://localhost/results-portal`
- ✅ Sitemap loads: check `/robots.txt` and `/sitemap.xml`
- ✅ Images render (favicon, logos, avatars)
- ✅ Database queries work (try login with test credentials)
- ✅ Forms submit correctly

### Domain Submission
Once live:
1. Submit sitemaps to Google Search Console
2. Request indexing for homepage and key pages
3. Monitor crawl errors in Search Console
4. Track indexing progress over 1-2 weeks

---

**Last Updated**: May 26, 2026
**Environment**: Production Ready
**URL Helper Status**: ✅ Active on all pages

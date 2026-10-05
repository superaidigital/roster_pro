# Database setup

`schema.sql` contains **schema only**. Production data, password hashes, personal data,
tokens, and backups must never be committed to Git.

## Local setup

1. Create an empty MySQL/MariaDB database named `roster_pro_db`.
2. Import `database/schema.sql`.
3. Configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS`
   in the server environment.
4. Create the first administrator using a password hash generated locally:

```bash
php -r "echo password_hash('CHANGE_ME', PASSWORD_DEFAULT), PHP_EOL;"
```

Insert that hash through a protected local/admin migration process. Do not commit the
plaintext password or the generated production hash.

## Backups

Store backups outside the web root and outside Git. Encrypt them at rest and restrict
filesystem access to the service account responsible for backups.

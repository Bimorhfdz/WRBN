Taruh di sini certificate CA milik Aiven sebagai `ca.pem`.

  curl -o certs/ca.pem https://<host-aiven>/ca.pem

ca.pem boleh di-commit karena bukan rahasia. File .key TIDAK boleh di-commit
(.gitignore sudah mengecualikannya).

Nilainya dipakai lewat environment variable:

  MYSQL_ATTR_SSL_CA=<path absolut ke certs/ca.pem>

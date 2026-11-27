# Output setelah `terraform apply`.
# URL aplikasi + host MySQL boleh dicatat; password JANGAN disebar.

output "webapp_url" {
  description = "URL publik aplikasi (buka ini untuk demo semhas)."
  value       = "https://${azurerm_linux_web_app.main.default_hostname}"
}

output "webapp_name" {
  description = "Nama Web App — dipakai untuk deploy (GitHub Actions / az webapp)."
  value       = azurerm_linux_web_app.main.name
}

output "resource_group_name" {
  description = "Nama Resource Group."
  value       = azurerm_resource_group.main.name
}

output "mysql_fqdn" {
  description = "Hostname MySQL Flexible Server."
  value       = azurerm_mysql_flexible_server.main.fqdn
}

output "mysql_database" {
  description = "Nama database."
  value       = azurerm_mysql_flexible_database.main.name
}

output "mysql_admin_username" {
  description = "Username admin MySQL."
  value       = var.mysql_admin_login
}

output "mysql_admin_password" {
  description = "Password admin MySQL (dibuat acak oleh Terraform). Simpan baik-baik."
  value       = random_password.mysql_admin.result
  sensitive   = true
}

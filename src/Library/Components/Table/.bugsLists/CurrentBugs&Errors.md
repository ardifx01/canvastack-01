File: `vendor\incodiy\codiy\src\Controllers\Admin\System\UserController.php`
Bugs: 

1. Filter input hanya ada `username`, tidak ada filter untuk `group_info`, padahal sudah dipanggil dengan kode:

```php
$this->table->useRelation('group');

$this->table->filterGroups('username', 'selectbox', true);
$this->table->filterGroups('group_info', 'selectbox', true);
````
2. Setelah menekan filter button, ketika proses query filter sudah selesai, popup filter-box nya masih belum close, masih harus menunggu cukup lama agar auto closed.

File: `vendor\incodiy\codiy\src\Controllers\Admin\System\UserActivityController.php`
Bugs: Maximum execution time of 120 seconds exceeded. Dan sepertinya bug fixing sebelumnya untuk file ini, gagal. Tolong untuk dianalisa dan di cek ulang langsung dari browser, jika memungkinkan.
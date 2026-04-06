<?php
$this->title = 'Чат';
?>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        if (localStorage.getItem('token') === null) {
            window.location.href = '/';
        }
    });
</script>
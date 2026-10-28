    </main>
    <footer class="footer">&copy; <?= date('Y') ?> Port Management System.</footer>
  </div>
<script>
function previewAvatar(i){var f=i.files[0];if(!f)return;var r=new FileReader();r.onload=function(e){var p=i.parentElement.querySelector('.avatar-preview');p.innerHTML='<img src="'+e.target.result+'" alt="">'};r.readAsDataURL(f)}
</script>
</body>
</html>

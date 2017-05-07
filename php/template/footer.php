    </div>

    <div id="newHosts" class="footer"></div>

<script language="javascript" type="text/javascript">
$(function worker() {
  $.ajaxSetup ({
    cache: false,
    complete: function() {
      setTimeout(worker, 60000);
    }
  });
  
  $("#newHosts").load('test/ajaxtest.php');
});
</script>


</body>
</html>


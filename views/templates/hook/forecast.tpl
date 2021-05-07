{if $error == ""}
  <div class="weatherforecastcontainer text-primary">
    {$weatherMain}, {$weatherDescription} - temperature {$temperature}° C, wind {$wind}, {$city} ({$country})
  </div>
{else}
  <div class="weatherforecastcontainer text-danger">
    {$error}
  </div>
{/if}
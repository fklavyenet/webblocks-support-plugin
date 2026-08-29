<?php

namespace WebBlocks\Support;

final class SupportTranslator
{
  public function admin(string $key, ?string $locale = null, array $replace = []): string
  {
    $original = $key;
    $key = str_starts_with($key, 'support.') ? substr($key, 8) : $key;
    $translated = trans('webblocks-support::admin.'.$key, $replace, $locale);

    return $translated === 'webblocks-support::admin.'.$key ? $original : $translated;
  }
}

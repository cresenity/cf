<?php
defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @since Dec 6, 2020
 */

?>
@props(['id' => null, 'maxWidth' => null])



<div {{ $attributes }}>
    <div id="{{ $id }}" x-data="{ show: @entangle($attributes->cf('model')) }"
        x-show="show"
        x-on:close.stop="show = false"
        x-on:keydown.escape.window="show = false"
        class="cf-modal-wrapper"
        style="display: none;">
        <div x-show="show" class="cf-modal-wrapper-inner" >
            <!--
              Background overlay, show/hide based on modal state.

              Entering: "ease-out duration-300"
                From: "opacity-0"
                To: "opacity-100"
              Leaving: "ease-in duration-200"
                From: "opacity-100"
                To: "opacity-0"
            -->
            <div class="cf-modal-overlay" x-on:click="show = false" x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0">
                <div class="cf-modal-overlay-inner"></div>
            </div>

            <!-- This element is to trick the browser into centering the modal contents. -->
            <span class="cf-modal-center-helper" aria-hidden="true">&#8203;</span>
            <!--
              Modal panel, show/hide based on modal state.

              Entering: "ease-out duration-300"
                From: "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                To: "opacity-100 translate-y-0 sm:scale-100"
              Leaving: "ease-in duration-200"
                From: "opacity-100 translate-y-0 sm:scale-100"
                To: "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            -->
            <div x-show="show" class="cf-modal {{ $maxWidth }}"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>

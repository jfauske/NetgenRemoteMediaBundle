<div id="ngremotemedia-buttons-{$fieldId}" class="ngremotemedia-buttons"
    data-id="{$fieldId}"
    data-contentobject-id="{$contentObjectId}"
    data-version="{$version}">

    <input type="hidden" name="{$base}_media_id_{$fieldId}" value="{$value.resourceId}" class="media-id" />

    {if $value.resourceId}
        {if $type|eq('image')}
            {include uri="design:parts/overlay_action_button-1.tpl"}
        {/if}
        <input type="button" class="ngremotemedia-remove-file button" value="{'Remove media'|i18n( 'content/edit' )}">
    {/if}

    <input type="button" class="ngremotemedia-remote-file button" value="{'Choose from NgRemoteMedia'|i18n( 'content/edit' )}">

    <div class="ngremotemedia-local-file-container">
        <input type="button" class="ngremotemedia-local-file button upload-from-disk" value="{'Choose from computer'|i18n( 'content/edit' )}">
    </div>

    <div class="upload-progress hid" id="ngremotemedia-progress-{$fieldId}">
        <div class="progress"></div>
    </div>
</div>
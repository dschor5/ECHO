$(document).ready(function() {
   const avatarUpload = $('#avatar-upload');
   const avararPreview = $('#avatar-preview');
   avatarUpload.addEventListener("change", tempAvatarUpload);
});

function tempAvatarUpload() {
   const file = avatarUpload.files.file[0];
   avatarPreview.attr('src', URL.createObjectURL(file));
}
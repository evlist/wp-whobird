/**
 * Use this file for JavaScript code that you want to run in the front-end
 * on posts/pages that contain this block.
 *
 * When this file is defined as the value of the `viewScript` property
 * in `block.json` it will be enqueued on the front end of the site.
 *
 * Example:
 *
 * ```js
 * {
 *   "viewScript": "file:./view.js"
 * }
 * ```
 *
 * If you're not making any changes to this file because your project doesn't need any
 * JavaScript running in the front-end, then you should delete this file and remove
 * the `viewScript` property from `block.json`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#view-script
 */

/* eslint-disable no-console */
console.log( 'Hello World! (from wpwbd-wp-whobird block)' );
/* eslint-enable no-console */
document.addEventListener("DOMContentLoaded", function () {
  const listItems = document.querySelectorAll(".wpwbd_list li");
  const playPauseButton = document.getElementById("play-pause");
  const prevButton = document.getElementById("prev");
  const nextButton = document.getElementById("next");
  const currentTrackInfo = document.getElementById("current-track");

  let currentListItem = null;
  let recordings = [];
  let currentIndex = 0;
  let isPlaying = false;

  const audio = new Audio();

  const updateTrackInfo = () => {
    currentTrackInfo.textContent = `Track ${currentIndex + 1} / ${recordings.length} (${currentListItem.textContent})`;
  };

  const playAudio = () => {
    audio.play();
    isPlaying = true;
    playPauseButton.textContent = "⏸";
  };

  const pauseAudio = () => {
    audio.pause();
    isPlaying = false;
    playPauseButton.textContent = "▶️";
  };

  const loadRecordings = (listItem) => {
    currentListItem = listItem;
    recordings = listItem.getAttribute("data-recordings").split(",");
    currentIndex = 0;
    audio.src = recordings[currentIndex];
    updateTrackInfo();
  };

  playPauseButton.addEventListener("click", () => {
    if (isPlaying) {
      pauseAudio();
    } else {
      playAudio();
    }
  });

  prevButton.addEventListener("click", () => {
    if (!currentListItem) return;
    currentIndex = (currentIndex - 1 + recordings.length) % recordings.length;
    audio.src = recordings[currentIndex];
    updateTrackInfo();
    if (isPlaying) playAudio();
  });

  // Automatically play the next track when the current one ends
  audio.addEventListener("ended", () => {
    if (currentIndex < recordings.length -1 )
    {
      currentIndex = (currentIndex + 1) % recordings.length;
      audio.src = recordings[currentIndex];
      updateTrackInfo();
      playAudio(); // Automatically start playing the next track
    }
  });


  nextButton.addEventListener("click", () => {
    if (!currentListItem) return;
    currentIndex = (currentIndex + 1) % recordings.length;
    audio.src = recordings[currentIndex];
    updateTrackInfo();
    if (isPlaying) playAudio();
  });

  listItems.forEach((item) => {
    item.addEventListener("click", () => {
      loadRecordings(item);
      playAudio();
    });
  });

  // Initialize the player with the first list item if available
  if (listItems.length > 0) {
    loadRecordings(listItems[0]);
  }
});

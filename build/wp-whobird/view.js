/******/ (() => { // webpackBootstrap
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
/**
 * Refactored view.js for modularity.
 *
 * This script now separates the player logic and the Ajax queue logic
 * into two distinct functions, `initializePlayer` and `initializeAjaxQueue`,
 * which are both invoked when the DOM content is loaded.
 */

document.addEventListener("DOMContentLoaded", function () {
  initializePlayer();
  initializeAjaxQueue();
});

/**
 * Initializes the player logic for handling audio playback.
 */
function initializePlayer() {
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
    const trackText = "Track";
    currentTrackInfo.textContent = `${trackText} ${currentIndex + 1} / ${recordings.length} (${currentListItem.textContent})`;
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
  const loadRecordings = listItem => {
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
  audio.addEventListener("ended", () => {
    if (currentIndex < recordings.length - 1) {
      currentIndex = (currentIndex + 1) % recordings.length;
      audio.src = recordings[currentIndex];
      updateTrackInfo();
      playAudio();
    }
  });
  nextButton.addEventListener("click", () => {
    if (!currentListItem) return;
    currentIndex = (currentIndex + 1) % recordings.length;
    audio.src = recordings[currentIndex];
    updateTrackInfo();
    if (isPlaying) playAudio();
  });
  listItems.forEach(item => {
    item.addEventListener("click", () => {
      loadRecordings(item);
      playAudio();
    });
  });
  if (listItems.length > 0) {
    loadRecordings(listItems[0]);
  }
}

/**
 * Initializes the Ajax queue logic for updating bird entries.
 */
function initializeAjaxQueue() {
  let birdQueue = [];
  let isProcessing = false;

  /**
   * Add a bird ID to the queue for updating.
   * @param {string} ebirdId - The eBird ID of the bird.
   */
  function queueBirdForUpdate(ebirdId) {
    birdQueue.push(ebirdId);
    processBirdQueue();
  }

  /**
   * Process the queue by sending one request at a time.
   */
  function processBirdQueue() {
    if (isProcessing || birdQueue.length === 0) return;
    isProcessing = true;
    const ebirdId = birdQueue.shift();
    console.log(ajaxurl);
    // Send Ajax request using fetch
    fetch(ajaxurl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body: new URLSearchParams({
        action: "update_bird_data",
        ebird_id: ebirdId
      })
    }).then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    }).then(data => {
      if (data.success) {
        const birdEntry = document.querySelector(`.wpwbd-bird-entry[data-ebird-id="${ebirdId}"]`);
        if (birdEntry) {
          birdEntry.innerHTML = data.html;
        }
      }
    }).catch(() => {
      console.error(`Error updating bird data for ${ebirdId}.`);
    }).finally(() => {
      isProcessing = false;
      processBirdQueue(); // Process the next item in the queue
    });
  }

  // Find all bird entries with data-ebird-id and add them to the queue
  const birdEntries = document.querySelectorAll(".wpwbd-bird-entry[data-ebird-id]");
  birdEntries.forEach(entry => {
    const ebirdId = entry.getAttribute("data-ebird-id");
    queueBirdForUpdate(ebirdId);
  });
}
/******/ })()
;
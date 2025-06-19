// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

import { __ } from '@wordpress/i18n';

/**
 * JavaScript for the WhoBird block front-end view.
 *
 * Handles interactive features such as audio player controls and Ajax-based bird entry updates.
 * This file is enqueued on the front end via the block.json "viewScript" property.
 */

document.addEventListener("DOMContentLoaded", function () {
  initializePlayer();
  initializeAjaxQueue();
});

/**
 * Initializes the player logic for handling audio playback of bird recordings.
 * Sets up event listeners for play/pause, previous/next controls, and track selection.
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

  // Updates the UI with the current track information.
  const updateTrackInfo = () => {
    const trackText = __('Track', 'wp-whobird');
    currentTrackInfo.textContent = `${trackText} ${currentIndex + 1} / ${recordings.length} (${currentListItem.textContent})`;
  };

  // Plays the current audio track.
  const playAudio = () => {
    audio.play();
    isPlaying = true;
    playPauseButton.textContent = __('⏸', 'wp-whobird');
  };

  // Pauses the current audio track.
  const pauseAudio = () => {
    audio.pause();
    isPlaying = false;
    playPauseButton.textContent = __('▶️', 'wp-whobird');
  };

  // Loads recordings from the clicked list item.
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

  listItems.forEach((item) => {
    item.addEventListener("click", () => {
      loadRecordings(item);
      playAudio();
    });
  });

  // Load the first track by default if available.
  if (listItems.length > 0) {
    loadRecordings(listItems[0]);
  }
}

/**
 * Initializes the Ajax queue logic for updating bird entries asynchronously.
 * Ensures only one Ajax request is processed at a time to avoid race conditions.
 */
function initializeAjaxQueue() {
  let birdQueue = [];
  let isProcessing = false;

  /**
   * Adds a BirdNET ID to the update queue.
   * @param {string|number} birdnetId - The BirdNET ID of the bird.
   */
  function queueBirdForUpdate(birdnetId) {
    birdQueue.push(Number(birdnetId));
    processBirdQueue();
  }

  /**
   * Processes the update queue by sending one Ajax request at a time.
   */
  function processBirdQueue() {
    if (isProcessing || birdQueue.length === 0) return;

    isProcessing = true;
    const birdnetId = birdQueue.shift();

    // Send Ajax request using fetch to update bird data.
    fetch(ajaxurl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: new URLSearchParams({
        action: "update_bird_data",
        birdnet_id: birdnetId,
      }),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(__('HTTP error! status:', 'wp-whobird') + ` ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        if (data.success) {
          const birdEntry = document.querySelector(
            `.wpwbd-bird-entry[data-birdnet-id="${birdnetId}"]`
          );
          if (birdEntry) {
            birdEntry.innerHTML = data.data.html;
          }
        }
      })
      .catch(() => {
        console.error(__('Error updating bird data for', 'wp-whobird') + ` ${birdnetId}.`);
      })
      .finally(() => {
        isProcessing = false;
        processBirdQueue(); // Continue with the next item in the queue
      });
  }

  // Find all bird entries with a BirdNET ID and queue them for update.
  const birdEntries = document.querySelectorAll(
    ".wpwbd-bird-entry[data-birdnet-id]"
  );
  birdEntries.forEach((entry) => {
    const birdnetId = entry.getAttribute("data-birdnet-id");
    queueBirdForUpdate(birdnetId);
  });
}

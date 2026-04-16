/**
 * WooCommerce Organization Search
 * Modern ES6 implementation with keyboard navigation and loading states
 */
(function() {
  'use strict';

  const MIN_SEARCH_LENGTH = 4;
  const DEBOUNCE_MS = 500;
  const HIDE_RESULTS_DELAY = 200;

  class WicketWCOrgSearch {
    constructor() {
      this.searchInput = null;
      this.resultsContainer = null;
      this.hiddenIdInput = null;
      this.originalValue = '';
      this.debounceTimer = null;
      this.activeXhr = null;
      this.highlightIndex = -1;
      this.results = [];
      this.boundHandlers = {};
    }

    static init() {
      const instance = new WicketWCOrgSearch();
      instance.initialize();
      return instance;
    }

    initialize() {
      this.searchInput = document.getElementById('wc-org-search');
      if (!this.searchInput) return;

      this.resultsContainer = document.getElementById('wc-org-results');
      this.hiddenIdInput = document.getElementById('wc-org-search-id');

      if (!this.resultsContainer || !this.hiddenIdInput) return;

      this.bindEvents();
    }

    bindEvents() {
      // Store bound handlers for potential cleanup
      this.boundHandlers = {
        focus: this.handleFocus.bind(this),
        blur: this.handleBlur.bind(this),
        keydown: this.handleKeydown.bind(this),
        keyup: this.handleKeyup.bind(this),
        clickOutside: this.handleClickOutside.bind(this),
        resultClick: this.handleResultClick.bind(this),
        unsavedWarning: this.handleUnsavedWarning.bind(this),
      };

      this.searchInput.addEventListener('focus', this.boundHandlers.focus);
      this.searchInput.addEventListener('blur', this.boundHandlers.blur);
      this.searchInput.addEventListener('keydown', this.boundHandlers.keydown);
      this.searchInput.addEventListener('keyup', this.boundHandlers.keyup);

      document.addEventListener('click', this.boundHandlers.clickOutside);
      this.resultsContainer.addEventListener('click', this.boundHandlers.resultClick);
      this.resultsContainer.addEventListener('click', this.boundHandlers.unsavedWarning);
    }

    handleFocus() {
      this.originalValue = this.searchInput.value;
      this.searchInput.value = '';
    }

    handleBlur() {
      if (this.searchInput.value === '') {
        this.searchInput.value = this.originalValue;
      }
    }

    handleKeydown(e) {
      // Check if there are visible result items in the DOM
      const resultItems = this.resultsContainer.querySelectorAll('.result-item');

      if (resultItems.length > 0) {
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          this.navigateResults(1);
          return;
        }
        if (e.key === 'ArrowUp') {
          e.preventDefault();
          this.navigateResults(-1);
          return;
        }
        if (e.key === 'Enter' && this.highlightIndex >= 0) {
          e.preventDefault();
          this.selectResult(this.results[this.highlightIndex]);
          return;
        }
        if (e.key === 'Escape') {
          e.preventDefault();
          this.hideResults();
          return;
        }
      }

      // Enter fires search immediately
      if (e.key === 'Enter') {
        e.preventDefault();
        const searchTerm = this.searchInput.value.trim();
        if (searchTerm.length >= MIN_SEARCH_LENGTH) {
          clearTimeout(this.debounceTimer);
          this.doSearch(searchTerm);
        }
      }
    }

    handleKeyup(e) {
      if (['ArrowDown', 'ArrowUp', 'Enter', 'Escape'].includes(e.key)) {
        return;
      }

      const searchTerm = this.searchInput.value.trim();

      if (searchTerm.length < MIN_SEARCH_LENGTH) {
        clearTimeout(this.debounceTimer);
        if (this.activeXhr) {
          this.activeXhr.abort();
          this.activeXhr = null;
        }
        this.hideResults();
        return;
      }

      clearTimeout(this.debounceTimer);
      this.debounceTimer = setTimeout(() => {
        this.doSearch(searchTerm);
      }, DEBOUNCE_MS);
    }

    navigateResults(direction) {
      const items = this.resultsContainer.querySelectorAll('.result-item');
      if (items.length === 0) return;

      // Remove previous highlight
      if (this.highlightIndex >= 0 && items[this.highlightIndex]) {
        items[this.highlightIndex].classList.remove('highlight');
      }

      // Calculate new index
      this.highlightIndex = this.highlightIndex + direction;
      if (this.highlightIndex < 0) this.highlightIndex = items.length - 1;
      if (this.highlightIndex >= items.length) this.highlightIndex = 0;

      // Add new highlight
      items[this.highlightIndex].classList.add('highlight');
      items[this.highlightIndex].scrollIntoView({ block: 'nearest' });
    }

    doSearch(searchTerm) {
      // Abort previous request
      if (this.activeXhr) {
        this.activeXhr.abort();
        this.activeXhr = null;
      }

      // Show loading state
      this.showLoading();

      this.activeXhr = new XMLHttpRequest();
      this.activeXhr.open('POST', ajax_object.ajax_url, true);
      this.activeXhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

      this.activeXhr.onload = () => {
        const xhr = this.activeXhr;
        this.activeXhr = null;
        this.hideLoading();

        if (xhr && xhr.status === 200) {
          try {
            const response = JSON.parse(xhr.responseText);
            if (response.success && response.data) {
              this.results = response.data;
              this.displayResults(response.data);
            } else {
              this.showNoResults();
            }
          } catch (e) {
            console.error('WicketWCOrgSearch: Failed to parse response', e);
            this.showNoResults();
          }
        }
      };

      this.activeXhr.onerror = () => {
        this.activeXhr = null;
        this.hideLoading();
        this.showNoResults();
      };

      this.activeXhr.onabort = () => {
        this.activeXhr = null;
        this.hideLoading();
      };

      const nonceField = document.getElementById('wc_org_nonce_field');
      const params = new URLSearchParams({
        action: 'wc_org_search',
        term: searchTerm,
        nonce: nonceField ? nonceField.value : '',
      });

      this.activeXhr.send(params.toString());
    }

    displayResults(data) {
      if (!data || data.length === 0) {
        this.showNoResults();
        return;
      }

      this.resultsContainer.innerHTML = '';
      this.highlightIndex = -1;

      data.forEach((item) => {
        const div = document.createElement('div');
        div.className = 'result-item';
        div.textContent = item.name;
        div.dataset.id = item.id;
        div.setAttribute('role', 'option');
        this.resultsContainer.appendChild(div);
      });

      this.resultsContainer.style.display = 'block';
    }

    showLoading() {
      this.searchInput.classList.add('wicket-searching');
      this.resultsContainer.innerHTML = '<div class="loading"><span class="spinner is-active"></span> Searching...</div>';
      this.resultsContainer.style.display = 'block';
    }

    hideLoading() {
      this.searchInput.classList.remove('wicket-searching');
    }

    showNoResults() {
      this.resultsContainer.innerHTML = '<div class="no-results">No results found</div>';
      this.resultsContainer.style.display = 'block';
    }

    hideResults() {
      setTimeout(() => {
        this.resultsContainer.style.display = 'none';
        this.highlightIndex = -1;
        this.results = [];
      }, HIDE_RESULTS_DELAY);
    }

    handleResultClick(e) {
      const item = e.target.closest('.result-item');
      if (!item) return;

      this.selectResult({
        id: item.dataset.id,
        name: item.textContent,
      });
    }

    selectResult(item) {
      this.searchInput.value = item.name;
      this.hiddenIdInput.value = item.id;
      this.hideResults();
    }

    handleClickOutside(e) {
      if (!this.searchInput.contains(e.target) && !this.resultsContainer.contains(e.target)) {
        this.hideResults();
      }
    }

    handleUnsavedWarning() {
      this.searchInput.style.borderColor = 'orange';
      let preventUnload = true;

      const saveButtons = document.querySelectorAll('.save_order');
      saveButtons.forEach((button) => {
        const clickHandler = () => {
          preventUnload = false;
        };
        button.addEventListener('click', clickHandler);
      });

      window.addEventListener('beforeunload', function handler(e) {
        if (preventUnload) {
          const message = 'You have modified the org assignment for this order. Are you sure you want to leave without saving?';
          e.returnValue = message;
          return message;
        }
        window.removeEventListener('beforeunload', handler);
      });
    }

    destroy() {
      // Cleanup event listeners
      if (this.searchInput && this.boundHandlers.focus) {
        this.searchInput.removeEventListener('focus', this.boundHandlers.focus);
        this.searchInput.removeEventListener('blur', this.boundHandlers.blur);
        this.searchInput.removeEventListener('keydown', this.boundHandlers.keydown);
        this.searchInput.removeEventListener('keyup', this.boundHandlers.keyup);
      }

      if (this.resultsContainer && this.boundHandlers.resultClick) {
        this.resultsContainer.removeEventListener('click', this.boundHandlers.resultClick);
        this.resultsContainer.removeEventListener('click', this.boundHandlers.unsavedWarning);
      }

      if (this.boundHandlers.clickOutside) {
        document.removeEventListener('click', this.boundHandlers.clickOutside);
      }

      // Clear timers
      if (this.debounceTimer) {
        clearTimeout(this.debounceTimer);
      }

      // Abort active XHR
      if (this.activeXhr) {
        this.activeXhr.abort();
      }
    }
  }

  // Auto-initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      window.WicketWCOrgSearch = WicketWCOrgSearch.init();
    });
  } else {
    window.WicketWCOrgSearch = WicketWCOrgSearch.init();
  }
})();

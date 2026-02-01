document.addEventListener('DOMContentLoaded', function () {
    const searchForm = document.querySelector('#searchForm');
    const searchInput = document.querySelector('#searchInput');
    const searchButton = document.querySelector('#btnNavbarSearch');

    if (!searchForm || !searchInput) return;

    const searchResults = document.createElement('div');
    searchResults.id = 'searchResults';
    searchResults.className = 'search-results position-absolute w-100 bg-white shadow-lg mt-1 rounded';
    searchResults.style.display = 'none';
    searchResults.style.zIndex = '1060';
    searchResults.style.maxHeight = '400px';
    searchResults.style.overflowY = 'auto';

    // Insert the results container after the search form
    if (searchForm.parentNode) {
        searchForm.parentNode.insertBefore(searchResults, searchForm.nextSibling);
    }

    // Debounce function to limit how often the search runs
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Handle search
    const performSearch = debounce(async (query) => {
        if (!query.trim()) {
            searchResults.style.display = 'none';
            return;
        }

        try {
            const response = await fetch(`/ghost/api/search.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();

            if (data.results && data.results.length > 0) {
                searchResults.innerHTML = data.results.map(result => `
                    <a href="${result.url}" class="d-block p-3 border-bottom text-decoration-none text-dark hover-bg-light">
                        <div class="fw-bold">${result.title}</div>
                        <div class="text-muted small">${result.description || ''}</div>
                    </a>
                `).join('');
                searchResults.style.display = 'block';
            } else {
                searchResults.innerHTML = '<div class="p-3 text-muted">No results found</div>';
                searchResults.style.display = 'block';
            }
        } catch (error) {
            console.error('Search error:', error);
            searchResults.innerHTML = '<div class="p-3 text-danger">Error performing search</div>';
            searchResults.style.display = 'block';
        }
    }, 300);

    // Event listeners
    searchInput.addEventListener('input', (e) => {
        performSearch(e.target.value);
    });

    searchForm.addEventListener('submit', (e) => {
        e.preventDefault();
        performSearch(searchInput.value);
    });

    if (searchButton) {
        searchButton.addEventListener('click', () => {
            performSearch(searchInput.value);
        });
    }

    // Close search results when clicking outside
    document.addEventListener('click', (e) => {
        if (!searchForm.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    // Prevent search results from closing when clicking inside
    searchResults.addEventListener('click', (e) => {
        e.stopPropagation();
    });
});

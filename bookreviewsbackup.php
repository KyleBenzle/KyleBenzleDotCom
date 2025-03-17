
  <?php
  // Enable error reporting for debugging
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);

  // Start session for user tracking
  if (session_status() === PHP_SESSION_NONE) {
      session_start();
  }

  // Database connection settings
  $servername = "localhost";
  $username = "kyle_user";
  $password = "Mesaboogie52!";
  $dbname = "books";

  // Create connection
  $conn = new mysqli($servername, $username, $password, $dbname);

  // Check connection
  if ($conn->connect_error) {
      die("Connection failed: " . $conn->connect_error);
  }

  // Define variables
  $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
  $selectedGenre = isset($_GET['genre']) ? $_GET['genre'] : '';
  $selectedCollection = isset($_GET['collection']) ? $_GET['collection'] : '';
  $sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'default';
  $sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';

  // Validate sort parameters
  $allowedSortFields = ['book_title', 'book_author', 'book_year', 'book_star',
  'vote_up', 'default'];
  if (!in_array($sortBy, $allowedSortFields)) {
      $sortBy = 'default';
  }
  if ($sortOrder !== 'ASC' && $sortOrder !== 'DESC') {
      $sortOrder = 'DESC';
  }

  $books = null;
  $pageTitle = "My Top 100 Books of All Time";

  // Get genres for dropdown
  $genresResult = $conn->query("SELECT DISTINCT book_genre FROM books WHERE book_genre
  != '' ORDER BY book_genre");
  $genreOptions = "";
  if ($genresResult && $genresResult->num_rows > 0) {
      while ($genreRow = $genresResult->fetch_assoc()) {
          $genre = htmlspecialchars($genreRow['book_genre']);
          $selected = ($selectedGenre === $genreRow['book_genre']) ? 'selected' : '';
          $genreOptions .= "<option value=\"$genre\" $selected>$genre</option>";
      }
  }

  // Get collections for dropdown
  $collectionsResult = $conn->query("SELECT DISTINCT collection_name FROM books WHERE
  collection_name != '' ORDER BY collection_name");
  $collectionOptions = "";
  if ($collectionsResult && $collectionsResult->num_rows > 0) {
      while ($collectionRow = $collectionsResult->fetch_assoc()) {
          $collection = htmlspecialchars($collectionRow['collection_name']);
          $selected = ($selectedCollection === $collectionRow['collection_name']) ?
  'selected' : '';
          $collectionOptions .= "<option value=\"$collection\"
  $selected>$collection</option>";
      }
  }

  // Build Query - Start with base SELECT
  $query = "SELECT id, book_title, book_author, book_genre, book_year, book_star,
                   image_link, amazon_link, goodreads, mega_download, torrent_download,

                   post_text, collection_name, collection_rank, vote_up, vote_down
            FROM books";

  // Add WHERE clauses based on search/filter parameters
  if (!empty($searchTerm)) {
      $searchTerm = $conn->real_escape_string($searchTerm);
      $query .= " WHERE (book_title LIKE '%$searchTerm%' OR
                         book_author LIKE '%$searchTerm%' OR
                         book_genre LIKE '%$searchTerm%')";
      $pageTitle = "Search Results: " . htmlspecialchars($searchTerm);
  }
  elseif (!empty($selectedGenre)) {
      $selectedGenre = $conn->real_escape_string($selectedGenre);
      $query .= " WHERE book_genre = '$selectedGenre'";
      $pageTitle = htmlspecialchars($selectedGenre) . " Audiobooks";
  }
  elseif (!empty($selectedCollection)) {
      $selectedCollection = $conn->real_escape_string($selectedCollection);
      $query .= " WHERE collection_name = '$selectedCollection'";
      $pageTitle = htmlspecialchars($selectedCollection);
  }
  else {
      $query .= " WHERE book_year BETWEEN '2014' AND '2025'";
  }

  // Add ORDER BY clause for sorting
  if ($sortBy === 'default') {
      // First try to order by collection_rank if available, then fall back to vote_up
      $query .= " ORDER BY CASE WHEN collection_rank IS NULL OR collection_rank = ''
  THEN 1 ELSE 0 END,
                  collection_rank $sortOrder,
                  CAST(vote_up AS SIGNED) $sortOrder";
  }
  elseif ($sortBy === 'vote_up' || $sortBy === 'book_star') {
      // For voting and rating sorts, convert to number for proper ordering
      $query .= " ORDER BY CAST($sortBy AS SIGNED) $sortOrder";
  }
  else {
      $query .= " ORDER BY $sortBy $sortOrder";
  }

  $query .= " LIMIT 50";

  // Execute the query
  $books = $conn->query($query);

  // Function to generate sort URL
  function getSortUrl($field) {
      global $sortBy, $sortOrder, $searchTerm, $selectedGenre, $selectedCollection;

      $newOrder = ($sortBy === $field && $sortOrder === 'ASC') ? 'DESC' : 'ASC';

      $params = [];
      if (!empty($searchTerm)) $params['search'] = $searchTerm;
      if (!empty($selectedGenre)) $params['genre'] = $selectedGenre;
      if (!empty($selectedCollection)) $params['collection'] = $selectedCollection;

      $params['sort'] = $field;
      $params['order'] = $newOrder;

      return '?' . http_build_query($params);
  }

  // Function to generate sort icon
  function getSortIcon($field) {
      global $sortBy, $sortOrder;

      if ($sortBy === $field) {
          return ($sortOrder === 'ASC') ? ' ▲' : ' ▼';
      } elseif ($field === 'default' && $sortBy === 'default') {
          return ($sortOrder === 'ASC') ? ' ▲' : ' ▼';
      }
      return '';
  }

  // Function to render stars based on rating
  function renderStars($rating) {
      $rating = floatval($rating);
      $fullStars = floor($rating);
      $halfStar = ($rating - $fullStars) >= 0.5;
      $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);

      $html = '<div class="star-rating">';

      // Full stars
      for ($i = 0; $i < $fullStars; $i++) {
          $html .= '<span class="full-star">★</span>';
      }

      // Half star if needed
      if ($halfStar) {
          $html .= '<span class="half-star">★</span>';
      }

      // Empty stars
      for ($i = 0; $i < $emptyStars; $i++) {
          $html .= '<span class="empty-star">☆</span>';
      }

      $html .= ' <span class="rating-number">(' . $rating . ')</span>';
      $html .= '</div>';

      return $html;
  }

  // Function to get excerpt from post text
  function getExcerpt($text, $length = 144) {
      if (empty($text)) return '';

      $text = strip_tags($text);

      if (strlen($text) <= $length) {
          return $text;
      }

      $excerpt = substr($text, 0, $length);
      $lastSpace = strrpos($excerpt, ' ');

      if ($lastSpace !== false) {
          $excerpt = substr($excerpt, 0, $lastSpace);
      }

      return $excerpt . '...';
  }
  ?>

  <!DOCTYPE html>
  <html lang="en">
  <head>
      <title>AudioBook Reviews - <?php echo $pageTitle; ?></title>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <link rel="stylesheet"
  href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
      <link rel="stylesheet"
  href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">


    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.8/css/all.css"
      integrity="sha384-3AB7yXWz4OeoZcPbieVW64vVXEwADiYyAEhwilzWsLw+9FgqpyjjStpPnpBO8o8S"
      crossorigin="anonymous">



      <style>
          body {
              font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
              background-color: #f8f9fa;
              line-height: 1.6;
              color: #333;
          }
          .navbar {
              background-color: white;
              box-shadow: 0 2px 4px rgba(0,0,0,0.1);
          }
          .header-section {
              background: linear-gradient(135deg, #3a86ff 0%, #4361ee 100%);
              color: white;
              padding: 2rem 0;
              margin-bottom: 2rem;
              box-shadow: 0 4px 6px rgba(0,0,0,0.1);
          }
          .book-card {
              background-color: white;
              border-radius: 8px;
              box-shadow: 0 2px 5px rgba(0,0,0,0.1);
              margin-bottom: 20px;
              padding: 20px;
              transition: transform 0.2s, box-shadow 0.2s;
              border: 1px solid rgba(0,0,0,0.05);
          }
          .book-card:hover {
              transform: translateY(-5px);
              box-shadow: 0 8px 15px rgba(0,0,0,0.1);
          }
          .book-cover {
              width: 100px;
              max-height: 150px;
              object-fit: cover;
              border-radius: 4px;
              box-shadow: 0 3px 6px rgba(0,0,0,0.1);
          }
          .book-title {
              font-weight: 600;
              color: #3a86ff;
              font-size: 1.2rem;
              margin-bottom: 8px;
              text-decoration: none;
          }
          .book-title:hover {
              text-decoration: underline;
          }
          .book-info {
              color: #666;
              margin-bottom: 10px;
          }
          .book-excerpt {
              font-style: italic;
              color: #666;
              margin: 10px 0;
              font-size: 0.95rem;
              line-height: 1.5;
              background-color: #f9f9f9;
              padding: 10px;
              border-left: 3px solid #3a86ff;
              border-radius: 0 4px 4px 0;
          }
          .more-link {
              color: #3a86ff;
              text-decoration: none;
              font-weight: 600;
          }
          .custom-select {
              padding: 8px 10px;
              border-radius: 4px;
              border: 1px solid #ddd;
              background-color: white;
              box-shadow: 0 1px 3px rgba(0,0,0,0.1);
              transition: border-color 0.2s;
          }
          .custom-select:focus {
              border-color: #3a86ff;
              outline: none;
          }
          .search-box {
              border-radius: 25px;
              padding: 8px 15px;
              border: none;
              box-shadow: 0 2px 5px rgba(0,0,0,0.1);
              width: 100%;
              height: 46px;
          }
          .search-btn {
              border-radius: 0 25px 25px 0;
              background-color: #ff006e;
              color: white;
              border: none;
              padding: 12px 20px;
              height: 46px;
              position: relative;
              right: 3px;
          }
          .search-box:focus, .search-btn:focus {
              outline: none;
              box-shadow: 0 0 0 3px rgba(58, 134, 255, 0.3);
          }
          .sort-links {
              margin-bottom: 20px;
          }
          .sort-links a {
              color: #666;
              margin-right: 15px;
              text-decoration: none;
              transition: color 0.2s;
          }
          .sort-links a:hover {
              color: #3a86ff;
          }
          .sort-links a.active {
              color: #3a86ff;
              font-weight: 600;
          }
          .star-rating {
              color: #ffc107;
              font-size: 1.1rem;
          }
          .full-star, .half-star {
              color: #ffc107;
          }
          .empty-star {
              color: #e0e0e0;
          }
          .rating-number {
              color: #666;
              font-size: 0.85rem;
          }
          .vote-buttons {
              display: flex;
              align-items: center;
              justify-content: center;
              gap: 20px;
              margin-top: 15px;
          }
          .vote-btn {
              background: none;
              border: none;
              cursor: pointer;
              display: flex;
              flex-direction: column;
              align-items: center;
              color: #666;
              transition: all 0.2s;
          }
          .vote-btn:hover {
              transform: translateY(-2px);
          }
          .vote-btn:focus {
              outline: none;
          }
          .upvote:hover {
              color: #28a745;
          }
          .downvote:hover {
              color: #dc3545;
          }
          .vote-btn i {
              font-size: 1.5rem;
              margin-bottom: 3px;
          }
          .vote-count {
              font-size: 0.9rem;
              font-weight: 600;
          }
          .upvote.voted {
              color: #28a745;
          }
          .downvote.voted {
              color: #dc3545;
          }
          .book-links {
              margin-top: 15px;
          }
          .book-links a {
              display: inline-block;
              margin-right: 10px;
              margin-bottom: 5px;
              color: #3a86ff;
              transition: all 0.2s;
          }
          .book-links a:hover {
              color: #1c54b2;
              transform: translateY(-2px);
          }
          footer {
              background-color: #343a40;
              color: #adb5bd;
              padding: 3rem 0;
              margin-top: 3rem;
          }
          footer h5 {
              color: white;
              margin-bottom: 1rem;
          }
          footer a {
              color: #adb5bd;
              transition: color 0.2s;
          }
          footer a:hover {
              color: white;
              text-decoration: none;
          }
          .social-icons a {
              display: inline-block;
              width: 36px;
              height: 36px;
              background-color: rgba(255,255,255,0.1);
              border-radius: 50%;
              margin-right: 10px;
              text-align: center;
              line-height: 36px;
              transition: background-color 0.2s;
          }
          .social-icons a:hover {
              background-color: #3a86ff;
          }
          @media (max-width: 767px) {
              .book-cover {
                  width: 80px;
                  margin-bottom: 15px;
              }
              .book-card {
                  padding: 15px;
              }
              .sort-links {
                  display: flex;
                  flex-wrap: wrap;
                  justify-content: space-between;
              }
              .sort-links a {
                  margin-right: 5px;
                  margin-bottom: 5px;
                  font-size: 0.9rem;
              }
          }
      </style>
  </head>









  <body>
     <!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-light">
    <div class="container">
        <a class="navbar-brand" href="bookreviews.php">
            <img src="reviews-logo1.png" height="40" alt="Kyle's Book Reviews">
        </a>

        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="bookreviews.php"></a>
                </li>

                <!-- Genre Dropdown -->
                <li class="nav-item">
                    <form method="GET" action="bookreviews.php" class="form-inline my-2 my-lg-0">
                        <select name="genre" class="custom-select mr-sm-2" onchange="this.form.submit()">
                            <option value="">Browse by Genre</option>
                            <?php echo $genreOptions; ?>
                        </select>
                    </form>
                </li>

                <!-- Collection Dropdown -->
                <li class="nav-item">
                    <form method="GET" action="bookreviews.php" class="form-inline my-2 my-lg-0">
                        <select name="collection" class="custom-select mr-sm-2" onchange="this.form.submit()">
                            <option value="">Best Of Lists</option>
                            <?php echo $collectionOptions; ?>
                        </select>
                    </form>
                </li>
            </ul>
            <a class="nav-link" href="https://kylebenzle.com/faq.php"> What is this site? </a>

            <!-- Blog Home Right-Aligned -->
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                  
                    <a class="nav-link" href="https://kylebenzle.com"> Blog Home </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

      <header class="header-section">
          <div class="container">
              <div class="row align-items-center">
                  <div class="col-md-6 mb-3 mb-md-0">
                      <h1 class="h2"><?php echo $pageTitle; ?></h1>
                      <p class="lead">Search, browse by genre or see my "best of" lists.</p>
                  </div>

                  <div class="col-md-6">
                      <!-- Search Form -->
                      <form method="GET" action="bookreviews.php">
                          <div class="input-group">
                              <input type="text" class="form-control search-box"
  name="search"
                                     placeholder="Search by title, author, genre..."
                                     value="<?php echo htmlspecialchars($searchTerm);
  ?>">
                              <div class="input-group-append">
                                  <button class="btn search-btn" type="submit">
                                      <i class="fas fa-search"></i> Search
                                  </button>
                              </div>
                          </div>
                      </form>
                  </div>
              </div>
          </div>
      </header>

      <!-- Main Content -->
      <main class="container">
          <!-- Results Count & Sorting Options -->
          <div class="d-flex justify-content-between align-items-center mb-4">
              <h2 class="h4 mb-0">
                  Found <?php echo $books ? $books->num_rows : 0; ?> audiobooks
              </h2>

              <div class="sort-links">
                  <span class="text-muted mr-2">Sort by:</span>
                  <a href="<?php echo getSortUrl('default'); ?>" class="<?php echo
  $sortBy === 'default' ? 'active' : ''; ?>">
                      Popularity<?php echo getSortIcon('default'); ?>
                  </a>
                  <a href="<?php echo getSortUrl('book_title'); ?>" class="<?php echo
  $sortBy === 'book_title' ? 'active' : ''; ?>">
                      Title<?php echo getSortIcon('book_title'); ?>
                  </a>
                  <a href="<?php echo getSortUrl('book_author'); ?>" class="<?php echo
  $sortBy === 'book_author' ? 'active' : ''; ?>">
                      Author<?php echo getSortIcon('book_author'); ?>
                  </a>
                  <a href="<?php echo getSortUrl('book_year'); ?>" class="<?php echo
  $sortBy === 'book_year' ? 'active' : ''; ?>">
                      Year<?php echo getSortIcon('book_year'); ?>
                  </a>
                  <a href="<?php echo getSortUrl('vote_up'); ?>" class="<?php echo
  $sortBy === 'vote_up' ? 'active' : ''; ?>">
                      Rating<?php echo getSortIcon('vote_up'); ?>
                  </a>
              </div>
          </div>

          <!-- Books List -->
          <div class="books-list">
              <?php if ($books && $books->num_rows > 0): ?>
                  <?php
                  // Track processed IDs to avoid duplicates
                  $processedIds = [];

                  while($book = $books->fetch_assoc()):
                      // Skip if we've already processed this book ID
                      if (in_array($book['id'], $processedIds)) {
                          continue;
                      }
                      $processedIds[] = $book['id'];

                      // Get excerpt from post text
                      $excerpt = getExcerpt($book['post_text']);

                      // Get vote counts
                      $upvotes = !empty($book['vote_up']) ? intval($book['vote_up']) :
  0;
                      $downvotes = !empty($book['vote_down']) ?
  intval($book['vote_down']) : 0;
                  ?>
                      <div class="book-card">
                          <div class="row">
                              <div class="col-md-2 text-center">
                                  <a href="review.php?id=<?php echo $book['id']; ?>">
                                      <?php if (!empty($book['image_link'])): ?>
                                          <img src="<?php echo
  htmlspecialchars($book['image_link']); ?>" class="book-cover"
                                               alt="<?php echo
  htmlspecialchars($book['book_title']); ?>">
                                      <?php else: ?>
                                          <div class="book-cover d-flex
  align-items-center justify-content-center bg-light">
                                              <i class="fas fa-book fa-2x
  text-muted"></i>
                                          </div>
                                      <?php endif; ?>
                                  </a>

                                  <!-- Enhanced Vote Buttons with Thumb Icons -->
                                  <div class="vote-buttons">
                                      <button class="vote-btn upvote"
  data-book-id="<?php echo $book['id']; ?>"
                                              onclick="voteBook(<?php echo $book['id'];
   ?>, 'up')">
                                          <i class="fas fa-thumbs-up"></i>
                                          <span class="vote-count" id="upvote-<?php
  echo $book['id']; ?>"><?php echo $upvotes; ?></span>
                                      </button>
                                      <button class="vote-btn downvote"
  data-book-id="<?php echo $book['id']; ?>"
                                              onclick="voteBook(<?php echo $book['id'];
   ?>, 'down')">
                                          <i class="fas fa-thumbs-down"></i>
                                          <span class="vote-count" id="downvote-<?php
  echo $book['id']; ?>"><?php echo $downvotes; ?></span>
                                      </button>
                                  </div>

                                  <?php if (!empty($book['collection_rank']) && !empty($selectedCollection)): ?>
                                      <div class="mt-2">
                                          <span class="badge badge-info">Rank: <?php
  echo $book['collection_rank']; ?></span>
                                      </div>
                                  <?php endif; ?>
                              </div>

                              <div class="col-md-10">
                                  <a href="review.php?id=<?php echo $book['id']; ?>"
  class="book-title">
                                      <?php echo htmlspecialchars($book['book_title']);
   ?>
                                  </a>

                                  <div class="book-info">
                                      <strong>Author:</strong> <?php echo
  htmlspecialchars($book['book_author'] ?? 'Unknown'); ?>
                                      <?php if (!empty($book['book_genre'])): ?>
                                          <span class="mx-2">|</span>
  <strong>Genre:</strong> <?php echo htmlspecialchars($book['book_genre']); ?>
                                      <?php endif; ?>
                                      <?php if (!empty($book['book_year'])): ?>
                                          <span class="mx-2">|</span>
  <strong>Year:</strong> <?php echo htmlspecialchars($book['book_year']); ?>
                                      <?php endif; ?>
                                  </div>

                                  <!-- Star Rating -->
                                  <?php if (!empty($book['book_star'])): ?>
                                      <div class="mt-2">
                                          <?php echo renderStars($book['book_star']);
  ?>
                                      </div>
                                  <?php endif; ?>

                                  <!-- Review Excerpt -->
                                  <?php if (!empty($excerpt)): ?>
                                      <div class="book-excerpt">
                                          <?php echo $excerpt; ?>
                                          <a href="review.php?id=<?php echo
  $book['id']; ?>" class="more-link">(more)</a>
                                      </div>
                                  <?php endif; ?>

                                  <!-- External Links -->
                                  <div class="book-links">
                                      <?php if (!empty($book['amazon_link'])): ?>
                                          <a href="<?php echo
  htmlspecialchars($book['amazon_link']); ?>" target="_blank" class="btn btn-sm
  btn-outline-warning">
                                              <i class="fab fa-amazon"></i> Amazon
                                          </a>
                                      <?php endif; ?>

                                      <?php if (!empty($book['goodreads'])): ?>
                                          <a href="<?php echo
  htmlspecialchars($book['goodreads']); ?>" target="_blank" class="btn btn-sm
  btn-outline-secondary">
                                              <i class="fas fa-book"></i> Goodreads
                                          </a>
                                      <?php endif; ?>

                                      <?php if (!empty($book['mega_download'])): ?>
                                          <a href="<?php echo
  htmlspecialchars($book['mega_download']); ?>" target="_blank" class="btn btn-sm
  btn-outline-primary">
                                              <i class="fas fa-download"></i> Download
                                          </a>
                                      <?php endif; ?>

                                      <?php if (!empty($book['torrent_download'])): ?>
                                          <a href="<?php echo
  htmlspecialchars($book['torrent_download']); ?>" target="_blank" class="btn btn-sm
  btn-outline-success">
                                              <i class="fas fa-magnet"></i> Torrent
                                          </a>
                                      <?php endif; ?>
                                  </div>
                              </div>
                          </div>
                      </div>
                  <?php endwhile; ?>
              <?php else: ?>
                  <div class="alert alert-info">
                      <h4 class="alert-heading">No books found</h4>
                      <p class="mb-0">Try a different search term or browse by genre
  from the dropdown menu.</p>
                  </div>
              <?php endif; ?>
          </div>
      </main>

      <!-- Footer -->
      <footer>
          <div class="container">
              <div class="row">
                  <div class="col-lg-5">
                      <h5>About AudioBook Reviews</h5>
                      <p>An open blog for the best audiobooks free and uncensored.
  Find, review, discuss, vote on and download the best audiobooks online.</p>
                      <div class="social-icons mt-3">
                          <a href="#"><i class="fab fa-facebook-f"></i></a>
                          <a href="#"><i class="fab fa-twitter"></i></a>
                          <a href="#"><i class="fab fa-instagram"></i></a>
                          <a href="#"><i class="fab fa-reddit-alien"></i></a>
                      </div>
                  </div>
                  <div class="col-lg-3 offset-lg-1">
                      <h5>Quick Links</h5>
                      <ul class="list-unstyled">
                          <li class="mb-2"><a href="bookreviews.php">Home</a></li>
                          <li class="mb-2"><a href="faq.php">How to Use</a></li>
                          <li class="mb-2"><a href="contact.php">Contact</a></li>
                          <li class="mb-2"><a href="dmca.php">DMCA</a></li>
                      </ul>
                  </div>
                  <div class="col-lg-3">
                      <h5>Popular Categories</h5>
                      <ul class="list-unstyled">
                          <li class="mb-2"><a href="?genre=Science Fiction">Science
  Fiction</a></li>
                          <li class="mb-2"><a href="?genre=Fantasy">Fantasy</a></li>
                          <li class="mb-2"><a href="?genre=Mystery">Mystery &
  Thriller</a></li>
                          <li class="mb-2"><a href="?genre=Biography">Biography &
  Memoir</a></li>
                          <li class="mb-2"><a
  href="?genre=Self-help">Self-Help</a></li>
                      </ul>
                  </div>
              </div>
              <hr class="my-4 bg-secondary">
              <div class="row">
                  <div class="col-md-12 text-center">
                      <p class="mb-0">&copy; <?php echo date('Y'); ?> AudioBook
  Reviews. All rights reserved.</p>
                  </div>
              </div>
          </div>
      </footer>

      <!-- JavaScript Dependencies -->
      <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
      <script
  src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
      <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.
  js"></script>

      <!-- Voting Functionality -->
      <script>
          function voteBook(bookId, voteType) {
              // AJAX call to vote_ajax.php
              $.ajax({
                  url: 'vote_ajax.php',
                  type: 'POST',
                  data: {
                      id: bookId,
                      vote: voteType === 'up' ? 'up' : 'down'
                  },
                  dataType: 'json',
                  success: function(response) {
                      if (response) {
                          // Update vote counts
                          $('#upvote-' + bookId).text(response.vote_up || 0);
                          $('#downvote-' + bookId).text(response.vote_down || 0);

                          // Highlight the active vote button
                          if (voteType === 'up') {
                              $('.upvote[data-book-id="' + bookId +
  '"]').addClass('voted');
                              $('.downvote[data-book-id="' + bookId +
  '"]').removeClass('voted');
                          } else {
                              $('.downvote[data-book-id="' + bookId +
  '"]').addClass('voted');
                              $('.upvote[data-book-id="' + bookId +
  '"]').removeClass('voted');
                          }
                      }
                  },
                  error: function() {
                      alert('Error processing vote. Please try again.');
                  }
              });
          }
      </script>
  </body>
  </html>

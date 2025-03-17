  <?php
  // Start a session for user login functionality
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

  // Get book ID from URL
  $book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

  // Redirect to home if no valid ID provided
  if ($book_id <= 0) {
      header('Location: index.php');
      exit;
  }

  // Get book details
  $query = "SELECT * FROM books WHERE id = $book_id";
  $result = $conn->query($query);

  // Check if book exists
  if ($result->num_rows === 0) {
      header('Location: index.php');
      exit;
  }

  // Get book data
  $book = $result->fetch_assoc();

  // Function to handle voting
  function handleVote($conn, $book_id, $vote_type) {
      // In a real implementation, you'd track user IPs or user IDs to prevent duplicate votes
      // For simplicity, we're just incrementing the vote count here

      if ($vote_type === 'up') {
          $query = "UPDATE books SET vote_up = vote_up + 1 WHERE id = $book_id";
      } else {
          $query = "UPDATE books SET vote_down = vote_down + 1 WHERE id = $book_id";
      }

      $conn->query($query);

      // Get updated vote count
      $result = $conn->query("SELECT vote_up, vote_down FROM books WHERE id = $book_id");
      return $result->fetch_assoc();
  }

  // Check for vote action
  if (isset($_POST['vote']) && in_array($_POST['vote'], ['up', 'down'])) {
      $vote_result = handleVote($conn, $book_id, $_POST['vote']);
      // Return JSON response for AJAX request
      if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])
  === 'xmlhttprequest') {
          header('Content-Type: application/json');
          echo json_encode($vote_result);
          exit;
      }
      // Reload page to reflect new vote count for non-AJAX requests
      header("Location: review.php?id=$book_id");
      exit;
  }

  // Get recommended books in the same genre
  $genreRecommendations = [];
  if (!empty($book['book_genre'])) {
      $genre = $conn->real_escape_string($book['book_genre']);
      $query = "SELECT id, book_title, book_author, image_link FROM books
                WHERE book_genre = '$genre' AND id != $book_id AND book_star IS NOT NULL AND book_star != ''
                ORDER BY CAST(vote_up AS SIGNED) DESC LIMIT 4";
      $genreRecommendations = $conn->query($query);
  }

  // Get all unique genres for the dropdown menu (for the navbar)
  $genreQuery = "SELECT DISTINCT book_genre FROM books WHERE book_genre != '' ORDER BY book_genre";
  $genres = $conn->query($genreQuery);
  ?>

  <!DOCTYPE html>
  <html lang="en">
  <head>
      <title><?php echo htmlspecialchars($book['book_title']); ?> - AudioBook Review</title>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="description" content="Review of <?php echo htmlspecialchars($book['book_title']);
  ?> by <?php echo htmlspecialchars($book['book_author']); ?>">

      <!-- Bootstrap and Font Awesome for styling -->
      <link rel="stylesheet"
  href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
      <link rel="stylesheet"
  href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

      <style>
          body {
              font-family: 'Roboto', Arial, sans-serif;
              background-color: #f8f9fa;
              color: #333;
          }
          .navbar {
              box-shadow: 0 2px 4px rgba(0,0,0,.1);
          }
          .book-header {
              background-color: #fff;
              border-radius: 8px;
              box-shadow: 0 3px 10px rgba(0,0,0,0.1);
              padding: 2rem;
              margin-bottom: 2rem;
          }
          .book-cover {
              max-width: 100%;
              max-height: 400px;
              box-shadow: 0 5px 15px rgba(0,0,0,0.2);
          }
          .book-title {
              font-size: 2rem;
              font-weight: 600;
              margin-bottom: 0.5rem;
          }
          .book-author {
              font-size: 1.25rem;
              color: #6c757d;
              margin-bottom: 1rem;
          }
          .book-metadata {
              margin-bottom: 1.5rem;
          }
          .meta-item {
              margin-bottom: 0.5rem;
          }
          .star-rating {
              color: #ffc107;
              font-size: 1.25rem;
              margin-bottom: 1rem;
          }
          .book-action-links {
              display: flex;
              gap: 1rem;
              margin-bottom: 1.5rem;
          }
          .book-action-links a {
              display: flex;
              align-items: center;
              padding: 0.5rem 1rem;
              border-radius: 4px;
              text-decoration: none;
              color: #fff;
              transition: all 0.2s;
          }
          .book-action-links a:hover {
              transform: translateY(-2px);
              box-shadow: 0 4px 8px rgba(0,0,0,0.1);
          }
          .amazon-link {
              background-color: #FF9900;
          }
          .mega-link {
              background-color: #d9534f;
          }
          .goodreads-link {
              background-color: #5bc0de;
          }
          .torrent-link {
              background-color: #5cb85c;
          }
          .action-icon {
              margin-right: 0.5rem;
          }
          .vote-buttons {
              display: flex;
              gap: 1rem;
              margin-bottom: 1.5rem;
          }
          .vote-btn {
              display: flex;
              align-items: center;
              gap: 0.5rem;
              padding: 0.375rem 0.75rem;
              border-radius: 4px;
              border: 1px solid #ddd;
              background-color: #fff;
              cursor: pointer;
              transition: all 0.2s;
          }
          .vote-btn:hover {
              background-color: #f8f9fa;
          }
          .vote-btn.upvote {
              color: #28a745;
          }
          .vote-btn.downvote {
              color: #dc3545;
          }
          .review-content {
              background-color: #fff;
              border-radius: 8px;
              box-shadow: 0 3px 10px rgba(0,0,0,0.1);
              padding: 2rem;
              margin-bottom: 2rem;
          }
          .review-content h2 {
              margin-bottom: 1.5rem;
              border-bottom: 1px solid #eee;
              padding-bottom: 0.5rem;
          }
          .review-text {
              white-space: pre-line;
              line-height: 1.6;
          }
          .recommendations {
              background-color: #fff;
              border-radius: 8px;
              box-shadow: 0 3px 10px rgba(0,0,0,0.1);
              padding: 2rem;
          }
          .recommendations h3 {
              margin-bottom: 1.5rem;
              border-bottom: 1px solid #eee;
              padding-bottom: 0.5rem;
          }
          .recommendation-card {
              transition: transform 0.3s;
          }
          .recommendation-card:hover {
              transform: translateY(-5px);
          }
          .recommendation-img {
              height: 150px;
              object-fit: contain;
          }
          .genre-dropdown {
              max-height: 400px;
              overflow-y: auto;
          }
          footer {
              background-color: #343a40;
              color: white;
              padding: 2rem 0;
              margin-top: 3rem;
          }
          .footer-links a {
              color: #adb5bd;
              text-decoration: none;
              margin: 0 10px;
          }
          .footer-links a:hover {
              color: white;
          }
          @media (max-width: 768px) {
              .book-header {
                  text-align: center;
              }
              .book-cover {
                  margin-bottom: 1.5rem;
              }
          }
      </style>
  </head>

  <body>
      <!-- Navigation Bar -->
      <nav class="navbar navbar-expand-lg navbar-light bg-white">
          <div class="container">
              <a class="navbar-brand" href="index.php">
                  <img src="reviews-logo1.png" height="40" alt="AudioBook Reviews">
              </a>
              <button class="navbar-toggler" type="button" data-toggle="collapse"
  data-target="#navbarNav">
                  <span class="navbar-toggler-icon"></span>
              </button>
              <div class="collapse navbar-collapse" id="navbarNav">
                  <ul class="navbar-nav mr-auto">
                      <li class="nav-item">
                          <a class="nav-link" href="index.php">Home</a>
                      </li>
                      <li class="nav-item dropdown">
                          <a class="nav-link dropdown-toggle" href="#" id="genreDropdown"
  role="button" data-toggle="dropdown">
                              Genres
                          </a>
                          <div class="dropdown-menu genre-dropdown">
                              <?php if ($genres && $genres->num_rows > 0): ?>
                                  <?php while($genre = $genres->fetch_assoc()): ?>
                                      <a class="dropdown-item" href="index.php?genre=<?php echo
  urlencode($genre['book_genre']); ?>">
                                          <?php echo htmlspecialchars($genre['book_genre']); ?>
                                      </a>
                                  <?php endwhile; ?>
                              <?php endif; ?>
                          </div>
                      </li>
                      <li class="nav-item">
                          <a class="nav-link" href="faq.php">How to Use</a>
                      </li>
                  </ul>

                  <div class="my-2 my-lg-0">
                      <?php if(!isset($_SESSION['username'])): ?>
                          <a href="login.php" class="btn btn-outline-primary my-2 my-sm-0">Login /
  Register</a>
                      <?php else: ?>
                          <a href="post_blog.php" class="btn btn-primary my-2 my-sm-0 mr-2">Submit
  Review</a>
                          <a href="logout.php" class="btn btn-outline-secondary my-2
  my-sm-0">Logout</a>
                      <?php endif; ?>
                  </div>
              </div>
          </div>
      </nav>

      <!-- Main Content -->
      <div class="container my-5">
          <!-- Book Header Section -->
          <div class="book-header">
              <div class="row">
                  <div class="col-md-4 text-center">
                      <?php if (!empty($book['image_link'])): ?>
                          <img src="<?php echo htmlspecialchars($book['image_link']); ?>"
  class="book-cover" alt="<?php echo htmlspecialchars($book['book_title']); ?>">
                      <?php else: ?>
                          <div class="book-cover d-flex justify-content-center align-items-center
  bg-light" style="height: 300px;">
                              <span class="text-muted">No Image Available</span>
                          </div>
                      <?php endif; ?>
                  </div>
                  <div class="col-md-8">
                      <h1 class="book-title"><?php echo htmlspecialchars($book['book_title']);
  ?></h1>
                      <p class="book-author">By <?php echo htmlspecialchars($book['book_author'] ??
  'Unknown'); ?></p>

                      <?php if (!empty($book['book_star'])): ?>
                      <div class="star-rating">
                          <?php
                          $rating = (float)$book['book_star'];
                          for ($i = 1; $i <= 5; $i++) {
                              if ($i <= $rating) {
                                  echo '<i class="fas fa-star"></i>';
                              } elseif ($i > $rating && $i - $rating < 1) {
                                  echo '<i class="fas fa-star-half-alt"></i>';
                              } else {
                                  echo '<i class="far fa-star"></i>';
                              }
                          }
                          echo ' <span>(' . $rating . '/5)</span>';
                          ?>
                      </div>
                      <?php endif; ?>

                      <div class="book-metadata">
                          <div class="meta-item"><strong>Genre:</strong> <?php echo
  htmlspecialchars($book['book_genre'] ?? 'Not specified'); ?></div>

                          <?php if (!empty($book['book_year'])): ?>
                              <div class="meta-item"><strong>Year:</strong> <?php echo
  htmlspecialchars($book['book_year']); ?></div>
                          <?php endif; ?>

                          <?php if (!empty($book['book_length'])): ?>
                              <div class="meta-item"><strong>Length:</strong> <?php echo
  htmlspecialchars($book['book_length']); ?></div>
                          <?php endif; ?>

                          <?php if (!empty($book['book_narrator'])): ?>
                              <div class="meta-item"><strong>Narrator:</strong> <?php echo
  htmlspecialchars($book['book_narrator']); ?></div>
                          <?php endif; ?>

                          <?php if (!empty($book['book_publisher'])): ?>
                              <div class="meta-item"><strong>Publisher:</strong> <?php echo
  htmlspecialchars($book['book_publisher']); ?></div>
                          <?php endif; ?>
                      </div>

                      <div class="vote-buttons">
                          <form method="post" class="vote-form" data-book-id="<?php echo $book_id;
  ?>">
                              <button type="button" class="vote-btn upvote" data-vote="up">
                                  <i class="fas fa-thumbs-up"></i>
                                  <span class="vote-count upvote-count"><?php echo
  !empty($book['vote_up']) ? (int)$book['vote_up'] : '0'; ?></span>
                              </button>
                          </form>
                          <form method="post" class="vote-form" data-book-id="<?php echo $book_id;
  ?>">
                              <button type="button" class="vote-btn downvote" data-vote="down">
                                  <i class="fas fa-thumbs-down"></i>
                                  <span class="vote-count downvote-count"><?php echo
  !empty($book['vote_down']) ? (int)$book['vote_down'] : '0'; ?></span>
                              </button>
                          </form>
                      </div>

                      <div class="book-action-links">
                          <?php if (!empty($book['amazon_link'])): ?>
                              <a href="<?php echo htmlspecialchars($book['amazon_link']); ?>"
  target="_blank" class="amazon-link">
                                  <i class="fab fa-amazon action-icon"></i> Buy on Amazon
                              </a>
                          <?php endif; ?>

                          <?php if (!empty($book['mega_download'])): ?>
                              <a href="<?php echo htmlspecialchars($book['mega_download']); ?>"
  target="_blank" class="mega-link">
                                  <i class="fas fa-download action-icon"></i> Download (Mega)
                              </a>
                          <?php endif; ?>

                          <?php if (!empty($book['goodreads'])): ?>
                              <a href="<?php echo htmlspecialchars($book['goodreads']); ?>"
  target="_blank" class="goodreads-link">
                                  <i class="fas fa-book action-icon"></i> Goodreads
                              </a>
                          <?php endif; ?>

                          <?php if (!empty($book['torrent_download'])): ?>
                              <a href="<?php echo htmlspecialchars($book['torrent_download']); ?>"
  target="_blank" class="torrent-link">
                                  <i class="fas fa-magnet action-icon"></i> Torrent
                              </a>
                          <?php endif; ?>
                      </div>
                  </div>
              </div>
          </div>

          <!-- Review Content Section -->
          <?php if (!empty($book['post_text'])): ?>
          <div class="review-content">
              <h2><?php echo !empty($book['post_title']) ? htmlspecialchars($book['post_title']) :
  'Review'; ?></h2>
              <div class="review-text">
                  <?php echo $book['post_text']; ?>
              </div>
              <?php if (!empty($book['u_name'])): ?>
                  <div class="mt-4 text-right text-muted">
                      <small>Review by <?php echo htmlspecialchars($book['u_name']); ?></small>
                  </div>
              <?php endif; ?>
          </div>
          <?php endif; ?>

          <!-- Recommendations Section -->
          <?php if ($genreRecommendations && $genreRecommendations->num_rows > 0): ?>
          <div class="recommendations">
              <h3>More <?php echo htmlspecialchars($book['book_genre']); ?> Audiobooks</h3>
              <div class="row">
                  <?php while($recommendation = $genreRecommendations->fetch_assoc()): ?>
                      <div class="col-md-3 col-6 mb-4">
                          <a href="review.php?id=<?php echo $recommendation['id']; ?>"
  class="text-decoration-none">
                              <div class="card recommendation-card">
                                  <div class="text-center p-3">
                                      <?php if (!empty($recommendation['image_link'])): ?>
                                          <img src="<?php echo
  htmlspecialchars($recommendation['image_link']); ?>" class="recommendation-img" alt="<?php echo
  htmlspecialchars($recommendation['book_title']); ?>">
                                      <?php else: ?>
                                          <div class="recommendation-img d-flex
  justify-content-center align-items-center bg-light">
                                              <span class="text-muted">No Image</span>
                                          </div>
                                      <?php endif; ?>
                                  </div>
                                  <div class="card-body text-center">
                                      <h6 class="card-title"><?php echo
  htmlspecialchars($recommendation['book_title']); ?></h6>
                                      <p class="card-text small text-muted"><?php echo
  htmlspecialchars($recommendation['book_author']); ?></p>
                                  </div>
                              </div>
                          </a>
                      </div>
                  <?php endwhile; ?>
              </div>
          </div>
          <?php endif; ?>
      </div>

      <!-- Footer -->
      <footer>
          <div class="container">
              <div class="row">
                  <div class="col-md-6">
                      <h5>About AudioBook Reviews</h5>
                      <p>An open blog for the best audiobooks free and uncensored. Find, review,
  discuss, vote on and download the best audiobooks online.</p>
                  </div>
                  <div class="col-md-3">
                      <h5>Links</h5>
                      <ul class="list-unstyled">
                          <li><a href="faq.php">How to Use</a></li>
                          <li><a href="contact.php">Contact</a></li>
                          <li><a href="dmca.php">DMCA</a></li>
                      </ul>
                  </div>
                  <div class="col-md-3">
                      <h5>Follow Us</h5>
                      <div class="social-icons">
                          <a href="#" class="mr-2"><i class="fab fa-facebook fa-lg"></i></a>
                          <a href="#" class="mr-2"><i class="fab fa-twitter fa-lg"></i></a>
                          <a href="#" class="mr-2"><i class="fab fa-instagram fa-lg"></i></a>
                      </div>
                  </div>
              </div>
              <hr class="bg-light">
              <div class="row">
                  <div class="col-md-12 text-center">
                      <p class="mb-0">© <?php echo date('Y'); ?> AudioBook Reviews. All rights
  reserved.</p>
                  </div>
              </div>
          </div>
      </footer>

      <!-- Scripts -->
      <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
      <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

      <!-- Voting functionality -->
      <script>
          $(document).ready(function() {
              $('.vote-btn').click(function() {
                  const voteType = $(this).data('vote');
                  const bookId = <?php echo $book_id; ?>;

                  $.ajax({
                      url: 'review.php?id=' + bookId,
                      type: 'POST',
                      dataType: 'json',
                      data: { vote: voteType },
                      headers: { 'X-Requested-With': 'XMLHttpRequest' },
                      success: function(response) {
                          $('.upvote-count').text(response.vote_up);
                          $('.downvote-count').text(response.vote_down);
                      }
                  });
              });
          });
      </script>
  </body>
  </html>

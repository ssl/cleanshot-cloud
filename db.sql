CREATE TABLE `uploads` (
  `id` int(11) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `created_at` varchar(50) NOT NULL,
  `completed` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `uploads`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;
# Blog Content Integration Challenge

## Overview
This technical challenge assesses a candidate's ability to create a robust PHP application that integrates with external blog content APIs, implements filtering mechanisms, and follows clean code practices.

## Challenge Description
Create a service that fetches blog posts and comments from external APIs, combines them, and provides filtering capabilities. The solution should demonstrate:

- Object-Oriented Programming principles
- SOLID principles
- Design Patterns implementation
- Clean Code practices
- Error handling
- Unit Testing

### Technical Requirements
- PHP 8.1 or higher
- Composer
- PHPUnit for testing

### Functional Requirements
1. Create a service that:
   - Fetches posts from: `https://coderbyte.com/api/challenges/json/all-posts`
   - Fetches comments from: `https://coderbyte.com/api/challenges/json/all-comments`
   - Combines posts with their respective comments
   - Implements filtering capabilities
   - Provides sorting functionality
   - Returns JSON output

2. Implement filtering with the following operators:
   - Equals (=)
   - Between
   - Greater than (>)
   - Less than (<)
   - Greater than or equals (>=)
   - Less than or equals (<=)

3. Include proper error handling for:
   - API failures
   - Invalid filter parameters
   - Data validation

### Code Structure
- Use interfaces for dependency injection
- Implement proper separation of concerns
- Create reusable components
- Follow PSR-12 coding standards


## Example Usage
```php
$blogService = BlogService::instance()
    ->retrieveBlogContent()
    ->filter([
        new Filter(
            key: 'userId',
            operator: Filter::OPERATOR_EQUALS,
            value: 1,
        ),
        new Filter(
            key: 'created_at',
            operator: Filter::OPERATOR_BETWEEN,
            value: ['2021-01-02', '2024-01-02'],
        ),
    ])
    ->sort()
    ->toJson();
```

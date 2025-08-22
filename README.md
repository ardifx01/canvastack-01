# Incodiy/Codiy — Enterprise Admin Framework
(Transitioning to CanvasStack)

**Current Status**: Active Development - Phase 2 (Hardening & Architecture Refinement)
**Next Major Release**: Rebranding to canvastack/canvastack

## Overview
A comprehensive enterprise-grade framework for building robust administrative systems, featuring advanced server-side DataTables integration, dynamic form generation, sophisticated charting capabilities, and flexible templating systems. Built with scalability, security, and maintainability at its core.

### Key Strengths
- Enterprise-grade server-side DataTables with advanced filtering
- Dynamic form generation with comprehensive validation
- Flexible templating system with inheritance support
- Built-in security features and authentication
- Extensive configurability and customization options

## Core Components & Architecture

### DataTables Engine (Library/Components/Table)
- Advanced server-side processing with memory optimization
- Comprehensive filtering system (POST/GET with special character handling)
- Dynamic column management and relationship handling
- Custom data formatters and processors
- Legacy system compatibility layer

### Form Builder (Library/Components/Form)
- Dynamic form generation with 50+ field types
- Advanced validation with custom rules support
- AJAX submission and real-time validation
- File upload handling with image processing
- Multi-step form wizard support

### Template System (Library/Components/Template)
- Hierarchical template inheritance
- Dynamic layout composition
- Component-based structure
- Theme support with hot-reload
- Mobile-responsive frameworks integration

### Security Layer
- Role-based access control (RBAC)
- Permission management system
- Request validation and sanitization
- XSS protection and CSRF handling
- API authentication support

## Latest: Table Component Hardening & Performance (v2.2.2)
- Flexible default ordering: user can set order; fallback to id/primary-like columns; safe default maintained
- Search delay tuned to 500ms to reduce request spam
- Lazy column reflow on tab activation for multi-table pages
- Optional logging guard via config: set `datatables.debug=false` to silence verbose logs
- See detailed docs in: Library/Components/Table/docs/

## Documentation
- Table docs: vendor/incodiy/codiy/src/Library/Components/Table/docs/
  - README.md — feature overview and usage
  - GET_POST_FILTERING_BUGFIX_ANALYSIS_AND_HARDENING.md
  - TABLE_FEATURES_MATRIX.md
  - REFRACTORING_AND_HARDENING_PLAN.md
  - CHANGELOG.md
  - UNIVERSAL_DATA_SOURCE_GUIDE.md
  - API_REFERENCE.md

## Configuration
- data-providers.php — model registry, defaults, auto discovery

## Development Roadmap

### Phase 2: Architecture Hardening (Current)
- Comprehensive test suite implementation
- Configuration externalization and validation
- Performance optimization and memory footprint reduction
- Enhanced error handling and logging
- Security audit and improvements

### Phase 3: Advanced Features & Integrations
- Schema-aware auto-discovery system
- Advanced operator mapping for queries
- Relationship manifest and graph processing
- Real-time data updates via WebSocket
- Enhanced caching strategies

### Phase 4: Enterprise Ready
- Complete removal of table-specific logic
- Pipeline-based data processing
- Comprehensive testing (Unit, Integration, E2E)
- Enterprise deployment guides
- Performance benchmarking tools

## Transition to CanvasStack

### Technical Migration
- Namespace migration with backward compatibility
- Modern PHP 8.x features adoption
- PSR standards compliance
- Dependency injection optimization
- Service container integration

### Documentation & Support
- Comprehensive API documentation
- Migration guides for existing systems
- Best practices and architecture guides
- Performance optimization guides
- Security implementation guides

## Contributing
- Use feature branches; write tests for bugfixes and features
- PRs must update docs and changelog entries

## Versioning
- Semantic Versioning (MAJOR.MINOR.PATCH)
- CHANGELOG kept in Table docs for component-level; package-level will be added during rebrand

## License
MIT
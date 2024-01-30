package Database::links;

# The database associated with the links file

use Database::Abstraction;

our @ISA = ('Database::Abstraction');

sub _open {
	my $self = shift;

	return $self->SUPER::_open(sep_char => ',');
}

1;

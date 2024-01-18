package VWF::DB::links;

# The database associated with the links file

use VWF::DB;

our @ISA = ('VWF::DB');

sub _open {
	my $self = shift;

	return $self->SUPER::_open(sep_char => ',');
}

1;

/*

 $Id$

*/

BEGIN;

/*

	Domain definition

*/

CREATE DOMAIN varchar_ci AS varchar(255) NOT NULL DEFAULT ''::character varying;



/*

	Operation Functions

*/

CREATE FUNCTION _varchar_ci_equal(varchar_ci, varchar_ci) RETURNS boolean AS 'SELECT LOWER($1) = LOWER($2)' LANGUAGE SQL STRICT;

CREATE FUNCTION _varchar_ci_not_equal(varchar_ci, varchar_ci) RETURNS boolean AS 'SELECT LOWER($1) != LOWER($2)' LANGUAGE SQL STRICT;

CREATE FUNCTION _varchar_ci_less_than(varchar_ci, varchar_ci) RETURNS boolean AS 'SELECT LOWER($1) < LOWER($2)' LANGUAGE SQL STRICT;

CREATE FUNCTION _varchar_ci_less_equal(varchar_ci, varchar_ci) RETURNS boolean AS 'SELECT LOWER($1) <= LOWER($2)' LANGUAGE SQL STRICT;

CREATE FUNCTION _varchar_ci_greater_than(varchar_ci, varchar_ci) RETURNS boolean AS 'SELECT LOWER($1) > LOWER($2)' LANGUAGE SQL STRICT;

CREATE FUNCTION _varchar_ci_greater_equals(varchar_ci, varchar_ci) RETURNS boolean AS 'SELECT LOWER($1) >= LOWER($2)' LANGUAGE SQL STRICT;



/*

	Operators

*/

CREATE OPERATOR <(

  PROCEDURE = _varchar_ci_less_than,

  LEFTARG = varchar_ci,

  RIGHTARG = varchar_ci,

  COMMUTATOR = >,

  NEGATOR = >=,

  RESTRICT = scalarltsel,

  JOIN = scalarltjoinsel);



CREATE OPERATOR <=(

  PROCEDURE = _varchar_ci_less_equal,

  LEFTARG = varchar_ci,

  RIGHTARG = varchar_ci,

  COMMUTATOR = >=,

  NEGATOR = >,

  RESTRICT = scalarltsel,

  JOIN = scalarltjoinsel);



CREATE OPERATOR >(

  PROCEDURE = _varchar_ci_greater_than,

  LEFTARG = varchar_ci,

  RIGHTARG = varchar_ci,

  COMMUTATOR = <,

  NEGATOR = <=,

  RESTRICT = scalargtsel,

  JOIN = scalargtjoinsel);



CREATE OPERATOR >=(

  PROCEDURE = _varchar_ci_greater_equals,

  LEFTARG = varchar_ci,

  RIGHTARG = varchar_ci,

  COMMUTATOR = <=,

  NEGATOR = <,

  RESTRICT = scalargtsel,

  JOIN = scalargtjoinsel);



CREATE OPERATOR <>(

  PROCEDURE = _varchar_ci_not_equal,

  LEFTARG = varchar_ci,

  RIGHTARG = varchar_ci,

  COMMUTATOR = <>,

  NEGATOR = =,

  RESTRICT = neqsel,

  JOIN = neqjoinsel);



CREATE OPERATOR =(

  PROCEDURE = _varchar_ci_equal,

  LEFTARG = varchar_ci,

  RIGHTARG = varchar_ci,

  COMMUTATOR = =,

  NEGATOR = <>,

  RESTRICT = eqsel,

  JOIN = eqjoinsel,

  HASHES,

  MERGES,

  SORT1= <);


/*
	Table: 'phpbb_gallery_rates'
*/
CREATE SEQUENCE phpbb_gallery_rates_seq;

CREATE TABLE phpbb_gallery_rates (
	rate_image_id INT4 DEFAULT nextval('phpbb_gallery_rates_seq'),
	rate_user_id INT4 DEFAULT '0' NOT NULL CHECK (rate_user_id >= 0),
	rate_user_ip varchar(40) DEFAULT '' NOT NULL,
	rate_point INT4 DEFAULT '0' NOT NULL CHECK (rate_point >= 0)
);

CREATE INDEX phpbb_gallery_rates_rate_image_id ON phpbb_gallery_rates (rate_image_id);
CREATE INDEX phpbb_gallery_rates_rate_user_id ON phpbb_gallery_rates (rate_user_id);
CREATE INDEX phpbb_gallery_rates_rate_user_ip ON phpbb_gallery_rates (rate_user_ip);
CREATE INDEX phpbb_gallery_rates_rate_point ON phpbb_gallery_rates (rate_point);


COMMIT;